<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportBookEntry extends Model
{
    protected $fillable = [
        'report_book_id',
        'course_day_id',
        'entry_date',
        'text',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'submitted_at' => 'datetime',
        'status'       => 'integer',
    ];

    /**
     * Beziehungen
     */
    public function reportBook(): BelongsTo
    {
        return $this->belongsTo(ReportBook::class);
    }

    /**
     * Accessor für lesbaren Statusnamen
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            0 => 'Entwurf',
            1 => 'Eingereicht',
            2 => 'Geprüft',
            3 => 'Freigegeben',
            default => 'Unbekannt',
        };
    }

    /**
     * Accessor für formatiertes Datum (optional für UI)
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->entry_date
            ? Carbon::parse($this->entry_date)->format('d.m.Y')
            : '-';
    }

    /**
     * Scopes für einfache Filterung
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 0);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('status', 2);
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', 3);
    }
}
