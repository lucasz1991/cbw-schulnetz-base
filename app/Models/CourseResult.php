<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'person_id',
        'result',
        'updated_by',
    ];

    /**
     * Beziehungen
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
