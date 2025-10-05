<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseRating extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'participant_id',
        'is_anonymous',
        'kb_1','kb_2','kb_3',
        'sa_1','sa_2','sa_3',
        'il_1','il_2','il_3',
        'do_1','do_2','do_3',
        'message',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    /**
     * Beziehungen
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }


    /**
     * Durchschnittliche Bewertung berechnen (optional helper)
     */
    public function getAverageScoreAttribute(): ?float
    {
        $fields = [
            $this->kb_1, $this->kb_2, $this->kb_3,
            $this->sa_1, $this->sa_2, $this->sa_3,
            $this->il_1, $this->il_2, $this->il_3,
            $this->do_1, $this->do_2, $this->do_3,
        ];

        $values = array_filter($fields, fn($v) => !is_null($v));
        return count($values) ? round(array_sum($values) / count($values), 2) : null;
    }
}
