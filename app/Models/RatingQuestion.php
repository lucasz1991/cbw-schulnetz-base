<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InsuranceType;
use App\Models\RatingQuestionOption;

class RatingQuestion extends Model
{
    use HasFactory;

    /**
     * Mögliche Typen für die Bewertungsfragen:
     *
     * - text      → Freitextfeld (einzeilig oder mehrzeilig)
     * - number    → Zahleneingabe (z. B. Schadenhöhe, Anzahl Tage)
     * - select    → Auswahl aus einer Liste vordefinierter Optionen
     * - boolean   → Ja/Nein-Frage (z. B. "Wurde reguliert?")
     * - rating    → Sternebewertung (z. B. 1 bis 5 Sterne)
     * - date      → Datumsauswahl (z. B. Beginn oder Ende der Regulierung)
     *
     * Diese Typen steuern sowohl die Darstellung im Formular
     * als auch die Validierung und spätere Auswertung.
     */

    protected $fillable = [
        'title',
        'question_text',
        'type',
        'is_required',
        'meta',
        'help_text',
        'default_value',
        'is_active',
        'frontend_title',
        'frontend_description',
        'weight',
        'input_constraints',
        'read_only',
        'tags',
    ];

    protected $casts = [
        'meta' => 'array',
        'input_constraints' => 'array',
        'tags' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'read_only' => 'boolean',
    ];

    public function insuranceSubtypes()
    {
        return $this->belongsToMany(InsuranceSubtype::class)
                    ->withPivot('order_column', 'notes', 'visibility_conditions')
                    ->orderBy('insurance_type_rating_question.order_column');
    }

    public function options()
    {
        return $this->hasMany(RatingQuestionOption::class)->orderBy('order_column');
    }
}
