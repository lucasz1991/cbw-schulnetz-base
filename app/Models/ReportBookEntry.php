<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use App\Jobs\ReportBook\CheckReportBooks;

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


    protected static function booted(): void
    {
        static::updated(function (ReportBookEntry $entry) {
            // Nur reagieren, wenn sich der Status geändert hat
            if (! $entry->wasChanged('status')) {
                return;
            }

            // Nur interessant, wenn der neue Status "Eingereicht" (1) ist
            if ($entry->status !== 1) {
                return;
            }

            // Ohne ReportBook macht der Check keinen Sinn
            if (! $entry->report_book_id) {
                return;
            }

            $bookId = $entry->report_book_id;

            // Gibt es überhaupt Einträge zu diesem ReportBook?
            $hasAny = static::where('report_book_id', $bookId)->exists();
            if (! $hasAny) {
                return;
            }

            // Gibt es Einträge, die NICHT Status 1 haben?
            $hasNonSubmitted = static::where('report_book_id', $bookId)
                ->where('status', '<', 1)
                ->exists();

            // Wenn noch andere Stati existieren -> noch nicht komplett eingereicht
            if ($hasNonSubmitted) {
                return;
            }

            // An dieser Stelle: alle Einträge dieses ReportBooks haben Status 1
            // -> Job für genau dieses ReportBook starten
            CheckReportBooks::dispatch([$bookId])
                ->delay(now()->addMinutes(5));
        });
    }

    /**
     * Beziehungen
     */
    public function reportBook(): BelongsTo
    {
        return $this->belongsTo(ReportBook::class);
    }

        public function courseDay(): BelongsTo
    {
        return $this->belongsTo(CourseDay::class, 'course_day_id');
    }

    /**
     * Shortcut auf den Kurs über das zugehörige ReportBook
     */
    public function getCourseAttribute()
    {
        return $this->reportBook?->course;
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
