<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseParticipantEnrollment extends Pivot
{
    use HasFactory, SoftDeletes;

    protected $table = 'course_participant_enrollments';
    public $incrementing = true;      // falls es eine autoincrement id gibt
    protected $keyType = 'int';

    protected $fillable = [
        'course_id','person_id',
        'teilnehmer_id','tn_baustein_id','baustein_id',
        'klassen_id','termin_id','vtz','kurzbez_ba',
        'status','is_active',
        'results','notes',  
        'source_snapshot','source_last_upd','last_synced_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'results'          => 'array', 
        'notes'            => 'array',
        'source_snapshot'  => 'array',
        'source_last_upd'  => 'datetime',
        'last_synced_at'   => 'datetime',
    ];

    public function course() { return $this->belongsTo(Course::class); }
    public function person() { return $this->belongsTo(Person::class); }

    // Beispiele für kleine Helfer
    public function addResult(array $entry): self
    {
        $results = $this->results ?? [];
        $results[] = $entry;
        $this->results = $results;
        return tap($this)->save();
    }

    public function addNote(array $note): self
    {
        $notes = $this->notes ?? [];
        $notes[] = $note + ['ts' => now()->toIso8601String()];
        $this->notes = $notes;
        return tap($this)->save();
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeByKlassenId($q, string $id) { return $q->where('klassen_id', $id); }
    public function scopeByTerminId($q, string $id) { return $q->where('termin_id', $id); }
}
