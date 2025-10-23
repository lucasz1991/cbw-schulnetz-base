<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class CourseMaterialAcknowledgement extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'person_id',
        'enrollment_id',
        'acknowledged_at',
        'signature_path',
        'signature_hash',
        'meta',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(CourseParticipantEnrollment::class, 'enrollment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getAcknowledgedAtFmtAttribute(): ?string
    {
        return $this->acknowledged_at
            ? Carbon::parse($this->acknowledged_at)->locale('de')->isoFormat('LLL')
            : null;
    }
}
