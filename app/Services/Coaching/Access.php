<?php

namespace App\Services\Coaching;

use App\Models\{CoachingContract, CourseDay, Setting};
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Access
{
    public static function enabled(): bool
    {
        // Shared DB is authoritative even when Base/Admin have separate or long-lived caches.
        if (!Schema::hasTable('settings')) return false;
        return filter_var(Setting::getValueUncached('coaching', 'enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function available(): bool
    {
        return self::enabled() && Schema::hasTable('coaching_contracts') && Schema::hasTable('coaching_notices');
    }

    public static function canUsePlanning(?\App\Models\User $user): bool
    {
        // The rollout switch alone never makes an ordinary participant a coaching participant.
        return $user && self::available() && CoachingContract::forUser($user)->exists();
    }

    public static function hasActiveStatus(array $status): bool
    {
        if (empty($status['coaching_contracts']) || !self::enabled()) return false;
        $today = now('Europe/Berlin')->toDateString();
        foreach ($status['coaching_contracts'] ?? [] as $row) {
            if (in_array(($row['status'] ?? ''), ['draft', 'active'], true) && (empty($row['cancelled_on']) || $row['cancelled_on'] >= $today)
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

    public static function guardTutorCourse(int $courseId): void
    {
        $course = \App\Models\Course::findOrFail($courseId);
        if ($course->type !== 'coaching') return;
        abort_unless(self::available() && auth()->user()?->persons()->whereKey($course->primary_tutor_person_id)->exists(), 403);
    }

    public static function guardAttendanceParticipant(CourseDay $day, int $participantId): void
    {
        if ($day->course?->type !== 'coaching') return;
        abort_unless($day->course->participants()->whereKey($participantId)->exists(), 403);
    }

    public static function guardDayWrite(CourseDay $day, bool $markStarted = true): void
    {
        if (!$day->exists || $day->course?->type !== 'coaching') return;
        if (!self::available()) throw ValidationException::withMessages(['coaching' => 'Einzelcoaching ist derzeit deaktiviert.']);
        $contract = CoachingContract::where('course_id', $day->course_id)->first();
        $plan = $contract?->confirmedPlan;
        if (!$contract || !$contract->course_id || !$plan?->confirmed_at || $contract->contract_status !== 'active'
            || !$contract->participant_person_id || $plan->participant_person_id !== $contract->participant_person_id
            || !$contract->tutor_person_id || $plan->tutor_person_id !== $contract->tutor_person_id
            || (!$contract->cancelled_on && $plan->contract_version !== $contract->contract_version)
            || ($contract->cancelled_on && $day->date->gt($contract->cancelled_on))) {
            throw ValidationException::withMessages(['coaching' => 'Der Einzelcoaching-Baustein ist noch nicht freigegeben oder der Vertrag wurde beendet.']);
        }
        // Session notes and documented topics belong to the normal teaching workflow; the agreed timetable stays fixed.
        $schedule = fn ($sessions) => array_map(function ($session) {
            $fixed = array_diff_key($session, ['notes' => true, 'topic' => true]);
            ksort($fixed);
            return $fixed;
        }, (array)$sessions);
        if ($day->isDirty(['date', 'start_time', 'end_time', 'std'])
            || ($day->isDirty('day_sessions') && $schedule($day->day_sessions) !== $schedule($day->getOriginal('day_sessions')))) {
            throw ValidationException::withMessages(['coaching' => 'Die verbindlich vereinbarten Termine dürfen nicht über die Bausteindokumentation geändert werden.']);
        }
        if ($markStarted && !$contract->started_at && $day->date->lte(today('Europe/Berlin'))) $contract->update(['started_at' => now()]);
    }
}
