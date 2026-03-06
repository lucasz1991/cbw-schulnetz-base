<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\File;

class ReportBook extends Model
{
    protected $fillable = [
        'user_id',
        'massnahme_id',
        'course_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Beziehungen
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Zugehöriger Kurs */
    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(CourseDay::class, 'course_id', 'course_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReportBookEntry::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }


    /**
     * Hilfsmethode: neuestes Entry abrufen
     */
    public function latestEntry(): ?ReportBookEntry
    {
        return $this->entries()->orderByDesc('entry_date')->first();
    }

    public function hasCompleteEntries(): bool
    {
        $expectedDays = $this->days()->count();

        if ($expectedDays === 0) {
            return false;
        }

        $existingDays = $this->entries()
            ->distinct('course_day_id')
            ->count('course_day_id');

        return $existingDays >= $expectedDays;
    }

    public function isFullySubmitted(): bool
    {
        if (! $this->hasCompleteEntries()) {
            return false;
        }

        return $this->entries()->exists()
            && ! $this->entries()->where('status', '<', 1)->exists();
    }

    public function isFullyReviewed(): bool
    {
        if (! $this->hasCompleteEntries()) {
            return false;
        }

        return $this->entries()->exists()
            && ! $this->entries()->where('status', '!=', 2)->exists();
    }

    public function participantSignatureFile(): ?File
    {
        return $this->files()
            ->where('type', 'sign_reportbook_participant')
            ->latest('id')
            ->first();
    }

    public function trainerSignatureFile(): ?File
    {
        return $this->files()
            ->where('type', 'sign_reportbook_trainer')
            ->latest('id')
            ->first();
    }

    /**
     * Hilfsmethode: Status durch Einträge status 
     * == Sobald alle Einträge den Status "Entwurf" == 0 haben dann ist der Status "fertig" 
     * == Sobald alle Einträge den Status "Eingereicht" == 1 haben dann ist der Status "Eingereicht" 
     * == Sobald alle Einträge den Status "Geprüft" == 2 haben dann ist der Status "Geprüft" 
     */
    public function getStatusAttribute(): string
    {
        $statuses = $this->entries()
            ->pluck('status')
            ->map(fn ($status) => (int) $status);

        if ($statuses->isEmpty()) {
            return '0';
        }

        if ($statuses->contains(3)) {
            return '3';
        }

        // "Eingereicht/Freigegeben" erst, wenn fuer alle Kurstage Eintraege vorhanden sind.
        if (! $this->hasCompleteEntries()) {
            return '0';
        }

        if ($statuses->every(fn (int $status) => $status === 2)) {
            return '2';
        }

        if ($statuses->every(fn (int $status) => $status >= 1) && $statuses->contains(1)) {
            return '1';
        }

        return '0';
    }

    public function isSubmitted(): bool
    {
        return $this->status === '1';
    }

    public function isReviewed(): bool
    {
        return $this->status === '2';
    }

    public function isRejected(): bool
    {
        return $this->status === '3';
    }

    public function latestSubmittedAt()
    {
        return $this->entries()
            ->whereIn('status', [1, 2, 3])
            ->max('submitted_at');
    }

    /**
     * Hilfsmethode: Alle ReportBooks und deren Einträge Geprüft 
     * == Bool 
     */
    public function areAllReportBooksReviewed(): bool
    {
        $books = static::query()
            ->with(['entries:id,report_book_id,course_day_id,status', 'days:id,course_id'])
            ->where('user_id', $this->user_id)
            ->get();

        if ($books->isEmpty()) {
            return false;
        }

        return $books->every(fn (self $book) => $book->isFullyReviewed());
    }

    public function getAreAllReportBooksReviewedAttribute(): string
    {
        return $this->areAllReportBooksReviewed() ? '1' : '0';
    }

    public function isReportBookReviewed(): bool
    {
        return $this->isFullyReviewed();
    }

    public function signature(string $type)
    {
        return $this->files()
            ->where('type', $type)
            ->latest()
            ->first();
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $s = $this->settings ?? [];
        $s[$key] = $value;
        $this->settings = $s;
    }

    /* ---------- Nützliche Helper/Scopes ---------- */

    public function scopeForCourse($q, int $courseId)
    {
        return $q->where('course_id', $courseId);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public static function getOrCreateFor(int $userId, int $courseId, string $massnahmeId): self
    {
        return static::firstOrCreate(
            [
                'user_id'   => $userId,
                'course_id' => $courseId,
                'massnahme_id' => $massnahmeId,
            ],
            [
                'title'        => 'Mein Berichtsheft',
            ]
        );
    }
}
