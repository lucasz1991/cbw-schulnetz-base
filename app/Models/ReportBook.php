<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportBook extends Model
{
    protected $fillable = [
        'user_id',
        'massnahme_id',
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

    public function entries(): HasMany
    {
        return $this->hasMany(ReportBookEntry::class);
    }

    /**
     * Optional: falls du ein Massnahme- oder Kursmodell hast
     */
    public function massnahme(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Massnahme::class, 'massnahme_id', 'id');
    }

    /**
     * Hilfsmethode: neuestes Entry abrufen
     */
    public function latestEntry(): ?ReportBookEntry
    {
        return $this->entries()->orderByDesc('entry_date')->first();
    }
}
