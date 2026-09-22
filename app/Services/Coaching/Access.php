<?php

namespace App\Services\Coaching;

use App\Models\{CoachingContract, CourseDay};
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Access
{
    public static function available(): bool
    {
        return (bool)config('coaching.enabled') && Schema::hasTable('coaching_contracts');
    }

    public static function hasActiveStatus(array $status): bool
    {
        if (empty($status['coaching_contracts']) || !config('coaching.enabled')) return false;
        $today = now('Europe/Berlin')->toDateString();
        foreach ($status['coaching_contracts'] ?? [] as $row) {
            if (($row['status'] ?? '') === 'active' && (empty($row['cancelled_on']) || $row['cancelled_on'] >= $today)
                && (empty($row['valid_until']) || $row['valid_until'] >= $today)) return true;
        }
        return false;
    }

    public static function participantForCourse(\App\Models\User $user, ?int $courseId = null, ?string $routeKey = null): ?\App\Models\Person
    {
        if (!self::available()) return null;
        if (!$courseId && !$routeKey) return null;
        $query = CoachingContract::whereIn('participant_person_id', $user->persons()->pluck('persons.id'));
        if ($courseId) $query->where('course_id', $courseId);
        else $query->whereHas('course', fn ($q) => $q->where('klassen_id', $routeKey));
        return $query->first()?->participant;
    }

    public static function courseIdsForPerson(int $id): array
    {
        if (!self::available()) return [];
        // Historical teaching/report books remain readable; writes are checked separately.
        return CoachingContract::where('participant_person_id', $id)->whereNotNull('course_id')->pluck('course_id')->all();
    }

    public static function guardDayWrite(CourseDay $day): void
    {
        if (!$day->exists || $day->course?->type !== 'coaching') return;
        $contract = CoachingContract::where('course_id', $day->course_id)->first();
        $plan = $contract?->confirmedPlan;
        if (!$contract || !$contract->course_id || !$plan?->confirmed_at || $contract->contract_status !== 'active'
            || (!$contract->cancelled_on && $plan->contract_version !== $contract->contract_version)
            || ($contract->cancelled_on && $day->date->gt($contract->cancelled_on))) {
            throw ValidationException::withMessages(['coaching' => 'Der Einzelcoaching-Baustein ist noch nicht freigegeben oder der Vertrag wurde beendet.']);
        }
        if ($day->isDirty(['date', 'start_time', 'end_time', 'day_sessions', 'std'])) throw ValidationException::withMessages(['coaching' => 'Die verbindlich vereinbarten Termine dürfen nicht über die Bausteindokumentation geändert werden.']);
        if (!$contract->started_at && $day->date->lte(today('Europe/Berlin'))) $contract->update(['started_at' => now()]);
    }
}
