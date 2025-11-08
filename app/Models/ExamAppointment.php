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
        'type',                 // 'intern' | 'extern'
        'name',
        'preis',
        'termin',
        'pflicht_6w_anmeldung',
    ];

    protected $casts = [
        'preis'                 => 'decimal:2',
        'termin'                => 'datetime',
        'pflicht_6w_anmeldung'  => 'boolean',
    ];

    /**
     * Praktisch für UI/Validierung: erlaubte Typen.
     */
    public const TYPES = ['intern', 'extern'];

    /**
     * Abgeleiteter Wert: Anmeldeschluss (= Termin minus 6 Wochen).
     */
    public function getAnmeldeschlussAttribute(): ?Carbon
    {
        return $this->termin ? Carbon::parse($this->termin)->subWeeks(6) : null;
    }
}
