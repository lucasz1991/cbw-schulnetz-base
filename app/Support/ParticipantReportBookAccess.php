<?php

namespace App\Support;

use App\Models\Person;
use App\Models\ReportBook;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Report books belong to ordinary participant courses, never to Einzelcoaching. */
class ParticipantReportBookAccess
{
    public static function courseIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $persons = $user->persons()->get();
        if ($persons->isEmpty()) {
            return [];
        }

        $query = DB::table('courses')
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->whereNull('courses.deleted_at')
            ->whereNull('cpe.deleted_at')
            ->where('cpe.is_active', 1)
            ->where(function ($query) use ($persons) {
                foreach ($persons as $person) {
                    $query->orWhere(fn ($scope) => CurrentParticipantCourseScope::applyForPerson($scope, $person));
                }
            });
        self::excludeCoaching($query);

        return $query->distinct()->pluck('courses.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function excludeCoaching($query, string $table = 'courses'): void
    {
        $query->where(fn ($q) => $q->whereNull($table.'.type')->orWhere($table.'.type', '!=', 'coaching'));
        $query->where(fn ($q) => $q->whereNull($table.'.vtz')->orWhere($table.'.vtz', '!=', 'E'));

        // Classification is independent of the global rollout switch and contract state.
        if (Schema::hasTable('coaching_contracts')) {
            $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('coaching_contracts as report_coaching')
                ->whereColumn('report_coaching.course_id', $table.'.id'));
        }
    }

    public static function isCoachingCourse(int $courseId): bool
    {
        $course = DB::table('courses')->where('id', $courseId)->first();
        if (($course->type ?? null) === 'coaching' || ($course->vtz ?? null) === 'E') {
            return true;
        }

        return Schema::hasTable('coaching_contracts')
            && DB::table('coaching_contracts')->where('course_id', $courseId)->exists();
    }

    public static function canAccessCourse(?User $user, int $courseId): bool
    {
        return $courseId > 0 && in_array($courseId, self::courseIds($user), true);
    }

    public static function canAccessBook(?User $user, ReportBook $book): bool
    {
        return $user && (int) $book->user_id === (int) $user->id
            && self::canAccessCourse($user, (int) $book->course_id);
    }

    public static function canUse(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $persons = $user->persons()->get();
        $hasCoaching = $persons->contains(fn (Person $person) => ! empty(data_get($person->statusdata, 'coaching_contracts'))
            || data_get($person->programdata, 'vtz') === 'E');
        if (! $hasCoaching && Schema::hasTable('coaching_contracts')) {
            $hasCoaching = DB::table('coaching_contracts')->whereIn('participant_person_id', $persons->pluck('id'))->exists();
        }
        if (! $hasCoaching) {
            $hasCoaching = DB::table('course_participant_enrollments as cpe')
                ->join('courses', 'courses.id', '=', 'cpe.course_id')
                ->whereIn('cpe.person_id', $persons->pluck('id'))
                ->where(fn ($q) => $q->where('courses.type', 'coaching')->orWhere('courses.vtz', 'E'))->exists();
        }

        // Preserve the ordinary empty state and legitimate parallel contracts.
        return ! $hasCoaching || self::courseIds($user) !== [];
    }

    public static function showNavigation(?User $user): bool
    {
        return $user && self::canUse($user)
            && $user->persons()->get()->contains(fn (Person $person) => $person->isEducation());
    }
}
