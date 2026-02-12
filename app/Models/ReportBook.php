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

    public function participantSignatureFile(): ?File
    {
        return $this->files()
            ->where('type', 'sign_reportbook_participant')
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
        $statuses = $this->entries()->pluck('status')->unique()->sort()->values();

        if ($statuses->count() === 1) {
            return (string) $statuses->first();
        }

        // Gemischte Stati
        if ($statuses->contains(3)) {
            return '3'; // Freigegeben
        } elseif ($statuses->contains(2)) {
            return '2'; // Geprüft
        } elseif ($statuses->contains(1)) {
            return '1'; // Eingereicht
        } else {
            return '0'; // Entwurf
        }
    }

    /**
     * Hilfsmethode: Alle ReportBooks und deren Einträge Geprüft 
     * == Bool 
     */
    public function areAllReportBooksReviewed(): bool
    {
        $userId = $this->user_id;

        // Keine ReportBooks => nicht "geprüft"
        if (!static::where('user_id', $userId)->exists()) {
            return false;
        }

        // Falls es ein ReportBook gibt, das entweder keine Einträge hat
        // oder mindestens einen Eintrag mit Status != 2 hat => nicht vollständig geprüft
        $hasUnreviewed = static::where('user_id', $userId)
            ->where(function ($q) {
            $q->whereDoesntHave('entries')
              ->orWhereHas('entries', function ($q2) {
                  $q2->where('status', '!=', 2);
              });
            })->exists();

        return !$hasUnreviewed;
    }

    public function getAreAllReportBooksReviewedAttribute(): string
    {
        return $this->areAllReportBooksReviewed() ? '1' : '0';
    }

    public function isReportBookReviewed(): string
    {
        $userId = $this->user_id;
        $courseId = $this->course_id;

        // Keine ReportBooks => nicht "geprüft"
        if (!static::where('user_id', $userId)->where('course_id', $courseId)->exists()) {
            return false;
        }

        // Falls es ein ReportBook gibt, das entweder keine Einträge hat
        // oder mindestens einen Eintrag mit Status != 2 hat => nicht vollständig geprüft
        $hasUnreviewed = static::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where(function ($q) {
            $q->whereDoesntHave('entries')
              ->orWhereHas('entries', function ($q2) {
                  $q2->where('status', '!=', 2);
              });
            })->exists();

        return !$hasUnreviewed;
    }

    public function getIsReportBookReviewedProperty(): bool
    {
        $reportBook = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->first();

        return $reportBook ? $reportBook->isReportBookReviewed : false;
    }

    public function signature(string $type)
    {
        return $this->files()
            ->where('type', $type)
            ->latest()
            ->first();
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
