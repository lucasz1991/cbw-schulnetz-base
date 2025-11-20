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
            ->where('type', 'participant_signature')
            ->latest('id')
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
