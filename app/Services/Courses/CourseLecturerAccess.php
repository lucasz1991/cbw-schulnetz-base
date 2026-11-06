<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** The same time-dependent lecturer rules are used by Base and Admin. */
class CourseLecturerAccess
{
    private static int $systemDepth = 0;

    public static function runAsSystem(callable $work)
    {
        self::$systemDepth++;
        try {
            return $work();
        } finally {
            self::$systemDepth--;
        }
    }

    public static function isSystemSync(): bool
    {
        return self::$systemDepth > 0;
    }

    public static function hasPlan(Course $course): bool
    {
        return $course->type !== 'coaching' && is_array($course->lecturer_plan)
            && ($course->lecturer_plan['status'] ?? 'legacy') !== 'legacy';
    }

    public static function healthy(Course $course): bool
    {
        return ! self::hasPlan($course) || (($course->lecturer_plan['status'] ?? null) === 'ready'
            && count(self::assignments($course)) > 0);
    }

    public static function assignments(Course $course): array
    {
        $rows = self::hasPlan($course) ? ($course->lecturer_plan['assignments'] ?? []) : [];

        return array_values(array_filter(is_array($rows) ? $rows : [], fn ($r) => is_array($r)));
    }

    public static function currentAssignment(Course $course, $date = null): ?array
    {
        if (! self::hasPlan($course) || ! self::healthy($course)) {
            return null;
        }
        $rows = self::assignments($course);
        $at = $date ? Carbon::parse($date, 'Europe/Berlin')->toDateString() : now('Europe/Berlin')->toDateString();
        foreach ($rows as $row) {
            if ($row['starts_on'] <= $at && $row['ends_on'] >= $at) {
                return $row;
            }
        }
        if ($date === null && $rows) {
            return $at < $rows[0]['starts_on'] ? $rows[0] : $rows[count($rows) - 1];
        }

        return null;
    }

    public static function currentPersonId(Course $course, $date = null): ?int
    {
        if (! self::hasPlan($course)) {
            return $course->getRawOriginal('primary_tutor_person_id')
                ? (int) $course->getRawOriginal('primary_tutor_person_id') : ($course->primary_tutor_person_id ? (int) $course->primary_tutor_person_id : null);
        }
        $row = self::currentAssignment($course, $date);

        return isset($row['person_id']) ? (int) $row['person_id'] : null;
    }

    public static function closingAssignment(Course $course): ?array
    {
        if (! self::hasPlan($course) || ! self::healthy($course)) {
            return null;
        }
        if ($course->lecturer_closing_assignment_id) {
            foreach (self::assignments($course) as $row) {
                if ($row['assignment_id'] === $course->lecturer_closing_assignment_id) {
                    return $row;
                }
            }

            return null;
        }
        $sourceId = data_get($course->lecturer_plan, 'closing_assignment_id');
        if ($sourceId) {
            foreach (self::assignments($course) as $row) {
                if ($row['assignment_id'] === $sourceId) {
                    return $row;
                }
            }

return null;
        }
        $completion = self::completionTime($course);
        if ($completion) {
            foreach (self::assignments($course) as $row) {
                if ($row['starts_on'] <= $completion->toDateString() && $row['ends_on'] >= $completion->toDateString()) {
                    return $row;
                }
            }
        }
        $rows = self::assignments($course);

        return $rows ? $rows[count($rows) - 1] : null;
    }

    public static function completionTime(Course $course): ?Carbon
    {
        $source = data_get($course->lecturer_plan, 'completion_at');
        if (is_string($source) && $source !== '') {
            try {
                return Carbon::parse($source)->setTimezone('Europe/Berlin');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $last = $course->days()->orderByDesc('date')->first();
        if ($last?->date && $last?->end_time) {
            return Carbon::parse($last->date->toDateString().' '.$last->end_time->format('H:i:s'), 'Europe/Berlin');
        }

        return $course->planned_end_date ? Carbon::parse($course->planned_end_date, 'Europe/Berlin')->endOfDay() : null;
    }

    public static function isCompleted(Course $course): bool
    {
        $end = self::completionTime($course);

        return $end !== null && now('Europe/Berlin')->gte($end);
    }

    public static function pinClosingAssignment(Course $course): ?array
    {
        abort_unless(self::isCompleted($course) && self::healthy($course), 403);
        if (! self::hasPlan($course)) {
            return null;
        }

        return DB::transaction(function () use ($course) {
            $fresh = Course::query()->lockForUpdate()->findOrFail($course->id);
            abort_unless(self::isCompleted($fresh) && self::healthy($fresh), 403);
            $assignment = self::closingAssignment($fresh);
            abort_unless($assignment, 403);
            if (! $fresh->lecturer_closing_assignment_id) {
                $fresh->forceFill(['lecturer_closing_assignment_id' => $assignment['assignment_id'], 'lecturer_closed_at' => now()])->save();
            }
            $course->setRawAttributes($fresh->getAttributes(), true);

            return $assignment;
        });
    }

    public static function personIds(?User $user): array
    {
        return $user ? $user->persons()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public static function canView(?User $user, Course $course): bool
    {
        $ids = self::personIds($user);
        if (! $ids) {
            return false;
        }
        if (! self::hasPlan($course)) {
            return in_array(self::currentPersonId($course), $ids, true);
        }

        return count(array_intersect($ids, array_map(fn ($r) => (int) ($r['person_id'] ?? 0), self::assignments($course)))) > 0
            || (! self::healthy($course) && in_array((int) $course->getRawOriginal('primary_tutor_person_id'), $ids, true));
    }

    public static function canManageCourse(?User $user, Course $course): bool
    {
        $course = self::freshForWrite($course);
        if (self::hasPlan($course) && (self::currentAssignment($course)['starts_on'] ?? '9999-12-31') > now('Europe/Berlin')->toDateString()) {
            return false;
        }

        return self::healthy($course) && in_array(self::currentPersonId($course), self::personIds($user), true);
    }

    public static function canWriteDay(?User $user, Course $course, CourseDay $day): bool
    {
        $course = self::freshForWrite($course);
        if ($day->exists) {
            $day = CourseDay::withoutEvents(fn () => CourseDay::query()->find($day->id)) ?? $day;
        }
        if ((int) $day->course_id !== (int) $course->id || ! self::canManageCourse($user, $course)) {
            return false;
        }

        return ! self::hasPlan($course) || in_array(self::currentPersonId($course, $day->date), self::personIds($user), true);
    }

    public static function canManageResults(?User $user, Course $course): bool
    {
        $course = self::freshForWrite($course);
        if (! self::healthy($course) || ! self::isCompleted($course)) {
            return false;
        }
        $assignment = self::closingAssignment($course);
        $personId = $assignment ? (int) $assignment['person_id'] : self::currentPersonId($course);

        return in_array($personId, self::personIds($user), true);
    }

    public static function canInvoice(?User $user, Course $course): bool
    {
        $course = self::freshForWrite($course);
        if (! self::hasPlan($course)) {
            return self::canView($user, $course);
        }
        if (! self::healthy($course)) {
            return false;
        }
        $ids = self::personIds($user);
        $today = now('Europe/Berlin')->toDateString();
        foreach (self::assignments($course) as $row) {
            if ($row['starts_on'] <= $today && in_array((int) $row['person_id'], $ids, true)) {
                return true;
            }
        }

        return false;
    }

    private static function freshForWrite(Course $course): Course
    {
        return $course->exists ? Course::withoutEvents(fn () => Course::query()->findOrFail($course->id)) : $course;
    }

    public static function display(Course $course): array
    {
        $rows = self::assignments($course);
        $now = now('Europe/Berlin')->toDateString();
        $ids = array_unique(array_column($rows, 'person_id'));
        $people = Person::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($rows as &$row) {
            $row['name'] = $people->get($row['person_id'])?->full_name
                ?: trim(($people->get($row['person_id'])?->vorname ?? '').' '.($people->get($row['person_id'])?->nachname ?? ''));
        }
        unset($row);
        $current = self::currentAssignment($course);
        if ($current) {
            foreach ($rows as $row) {
                if ($row['assignment_id'] === $current['assignment_id']) {
                    $current = $row;
                }
            }
        }

        return ['multiple' => count($ids) > 1, 'current' => $current, 'previous' => array_values(array_filter($rows, fn ($r) => $r['ends_on'] < $now)),
            'planned' => array_values(array_filter($rows, fn ($r) => $r['starts_on'] > $now)), 'assignments' => $rows,
            'conflict' => self::hasPlan($course) && ! self::healthy($course)];
    }

    public static function constrainVisibleCourses(Builder $query, $personIds): Builder
    {
        $ids = collect($personIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
        if (! \Illuminate\Support\Facades\Schema::hasColumn('courses', 'lecturer_plan')) {
            return $query->whereIn('primary_tutor_person_id', $ids);
        }

        return $query->where(function ($q) use ($ids) {
            $q->where(function ($legacy) use ($ids) {
                $legacy->whereIn('primary_tutor_person_id', $ids)->where(function ($state) {
                    $state->whereNull('lecturer_plan')->orWhere('lecturer_plan->status', 'legacy')
                        ->orWhere(fn ($empty) => $empty->where('lecturer_plan->status', 'conflict')->whereJsonLength('lecturer_plan->person_ids', 0));
                });
            });
            foreach ($ids as $id) {
                $q->orWhereJsonContains('lecturer_plan->person_ids',$id);
            }
        });
    }
}
