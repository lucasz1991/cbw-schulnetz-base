<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CoachingContract extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'notification_contacts' => 'array', 'participant_person_id' => 'integer', 'tutor_person_id' => 'integer',
        'confirmed_plan_id' => 'integer', 'course_id' => 'integer', 'institut_id' => 'integer',
        'valid_from' => 'date', 'valid_until' => 'date', 'cancelled_on' => 'date',
        'tutor_notified_user_id' => 'integer', 'tutor_notified_at' => 'datetime',
        'last_imported_at' => 'datetime', 'started_at' => 'datetime',
        'agreed_minutes' => 'integer', 'unit_minutes' => 'integer', 'revision' => 'integer',
    ];

    public function participant() { return $this->belongsTo(Person::class, 'participant_person_id'); }
    public function tutor() { return $this->belongsTo(Person::class, 'tutor_person_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function plans() { return $this->hasMany(CoachingPlan::class); }
    public function confirmedPlan() { return $this->belongsTo(CoachingPlan::class, 'confirmed_plan_id'); }
    public function latestPlan() { return $this->hasOne(CoachingPlan::class)->latestOfMany(); }
    public function notices() { return $this->hasMany(CoachingNotice::class); }
    public function messages() { return $this->hasMany(CoachingMessage::class); }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $ids = $user->persons()->pluck('persons.id');
        return $query->where(fn ($q) => $q->whereIn('participant_person_id', $ids)->orWhereIn('tutor_person_id', $ids));
    }

    public function planningAllowed(): bool
    {
        return in_array($this->contract_status, ['draft', 'active'], true) && !$this->cancelled_on
            && (!$this->valid_until || $this->valid_until->gte(today('Europe/Berlin')));
    }

    public function activeOn(?string $date = null): bool
    {
        $date ??= now('Europe/Berlin')->toDateString();
        return $this->contract_status === 'active'
            && (! $this->cancelled_on || $date <= $this->cancelled_on->toDateString())
            && (! $this->valid_until || $date <= $this->valid_until->toDateString());
    }

    public function hasCurrentConfirmedPlan(): bool
    {
        $plan = $this->confirmedPlan;
        return $plan && $plan->status === 'confirmed' && $plan->confirmed_at
            && $plan->contract_version === $this->contract_version && $plan->revision === $this->revision
            && $this->participant_person_id && $plan->participant_person_id === $this->participant_person_id
            && $this->tutor_person_id && $plan->tutor_person_id === $this->tutor_person_id;
    }

    public function startReady(): bool
    {
        return $this->course_id && $this->activeOn() && $this->hasCurrentConfirmedPlan();
    }

    public function getPlanningLabelAttribute(): string
    {
        if (! $this->planningAllowed()) return 'Vertrag beendet';
        if (! $this->tutor_person_id) return $this->uvs_tutor_person_id ? 'UVS-Dozent noch nicht verknüpft' : 'Dozent im UVS auswählen';
        if ($this->confirmedPlan && !$this->hasCurrentConfirmedPlan()) return 'Vertrag geändert – Prüfung durch Verwaltung erforderlich';
        if ($this->confirmed_plan_id && !$this->course_id) return $this->contract_status === 'draft' ? 'Gesamtplan bestätigt – Vertragsfreigabe im UVS ausstehend' : 'UVS-Rückmeldung ausstehend';
        if ($this->startReady()) return $this->started_at ? 'Baustein läuft' : 'Alle Termine bestätigt';
        return 'Gesamtplan abstimmen';
    }
}
