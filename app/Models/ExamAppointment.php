<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ExamAppointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'exam_appointments';

    protected $fillable = [
        'type',                    // intern | extern
        'name',
        'preis',
        'dates',                   // JSON: mehrere Termine
        'room',                    // neuer Raum
        'pflicht_6w_anmeldung',
        'course_id',               // falls du später per FK zu Kursen verknüpfst
    ];

    protected $casts = [
        'preis'                 => 'decimal:2',
        'dates'                 => 'array',    // wichtig!
        'pflicht_6w_anmeldung'  => 'boolean',
    ];

    /**
     * Erlaubte Typen
     */
    public const TYPES = ['intern', 'extern'];

    /**
     * Shortcut: gebe das erste Datum zurück
     */
    public function getFirstDateAttribute(): ?Carbon
    {
        if (! is_array($this->dates) || empty($this->dates)) {
            return null;
        }

        // Erwartung:
        // [
        //   ["datetime" => "2025-02-01 09:00:00"],
        //   ["datetime" => "2025-02-08 09:00:00"]
        // ]
        $first = $this->dates[0] ?? null;

        if (is_array($first)) {
            $value = $first['datetime']
                ?? $first['from']
                ?? null;

            return $value ? Carbon::parse($value) : null;
        }

        return null;
    }

    /**
     * Anmeldeschluss: 6 Wochen vor dem ersten Termin
     */
    public function getAnmeldeschlussAttribute(): ?Carbon
    {
        $first = $this->first_date;
        return $first ? $first->clone()->subWeeks(6) : null;
    }
}
