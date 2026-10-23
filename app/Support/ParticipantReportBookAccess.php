<?php

namespace App\Support;

use App\Models\Person;
use App\Models\ReportBook;
use App\Models\ReportBookEntry;
use App\Models\User;
use App\Services\ReportBooks\LocationPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Report books belong to ordinary participant courses, never to Einzelcoaching. */
class ParticipantReportBookAccess
{
    public static function courseIds(?User $user): array
    {
        return self::editableCourseIds($user, self::currentCourseIds($user));
    }

    private static function editableCourseIds(?User $user, array $courseIds): array
    {
        if (! $user || $courseIds === []) {
            return [];
        }
        // The course picker has no book/measure identity. A historic shared book must
        // not become editable merely because its disabled enrollment is now inactive.
        return array_values(array_filter($courseIds, fn (int $id) =>
            LocationPolicy::canWriteBook(new ReportBook(['user_id' => $user->id, 'course_id' => $id]))));
    }

    private static function currentCourseIds(?User $user): array
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
            && self::canAccessCourse($user, (int) $book->course_id)
            && LocationPolicy::canWriteBook($book);
    }

    public static function canWriteEntry(?User $user, ReportBookEntry $entry): bool
    {
        return ! self::isPreservedApproval($entry) && $entry->reportBook
            && self::canAccessBook($user, $entry->reportBook);
    }

    public static function isPreservedApproval(ReportBookEntry $entry): bool
    {
        return (bool) $entry->automated_approval_audit_id || (LocationPolicy::schemaReady() && $entry->exists
            && DB::table('report_book_policy_audit')->where('book_id', $entry->report_book_id)->where('entry_id', $entry->id)
                ->where('event', 'manual_signatures_preserved')
                ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first() !== null);
    }

    /** Serialize participant writes with policy publication and automatic approval. */
    public static function withWriteLock(User $user, ReportBook $context, callable $action, int $attempts = 3): mixed
    {
        return DB::transaction(function () use ($user, $context, $action) {
            abort_unless((int) $context->user_id === (int) $user->id, 403);
            $policy = LocationPolicy::lockForBook($context);
            abort_unless($policy['mode'] === 'normal', 403);

            $query = ReportBook::query()->where('user_id', $user->id);
            if ($context->exists) {
                $query->whereKey($context->id);
            } else {
                $query->where('course_id', $context->course_id)->where('massnahme_id', $context->massnahme_id);
            }
            $book = $query->lockForUpdate()->first();
            abort_if($context->exists && ! $book, 403);
            $book ??= $context;
            abort_unless((int) $book->course_id === (int) $context->course_id
                && $book->massnahme_id === $context->massnahme_id, 403);
            abort_unless(self::canAccessBook($user, $book), 403);
            if ($book->exists) {
                $book->entries()->orderBy('id')->lockForUpdate()->get();
            }
            // Re-evaluate after any lock wait; publication may have become effective.
            abort_unless(LocationPolicy::canWriteBook($book), 403);

            return $action($book);
        }, $attempts);
    }

    public static function canUse(?User $user): bool
    {
        return self::canUseEditor($user) || self::hasArchive($user);
    }

    /** The editor keeps the existing contract rules; archive access never grants write access. */
    public static function canUseEditor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $allPersons = $user->persons()->get();
        if ($allPersons->isEmpty()) {
            return ! self::hasArchive($user);
        }
        $persons = $allPersons->filter(fn (Person $person) =>
            LocationPolicy::forInstitute((int) $person->institut_id)['required']);
        if ($persons->isEmpty()) {
            return false;
        }
        $currentIds = self::currentCourseIds($user);
        $normalIds = self::editableCourseIds($user, $currentIds);
        if ($currentIds !== [] && $normalIds === []) {
            return false;
        }
        if ($currentIds === [] && self::hasArchive($user)) {
            return false;
        }
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
        return ! $hasCoaching || $normalIds !== [];
    }

    public static function showNavigation(?User $user): bool
    {
        return self::hasArchive($user) || ($user && self::canUseEditor($user)
            && $user->persons()->get()->contains(fn (Person $person) =>
                LocationPolicy::forInstitute((int) $person->institut_id)['required'] && $person->isEducation()));
    }

    public static function showLocationNotice(?User $user): bool
    {
        return $user && ! self::canUseEditor($user) && $user->persons()->get()->contains(fn (Person $person) =>
            $person->isEducation() && ! LocationPolicy::forInstitute((int) $person->institut_id)['required']);
    }

    /** Existing approved records are selected by book ID, never by a current enrollment. */
    public static function archiveBooks(?User $user): Collection
    {
        if (! $user) {
            return new Collection();
        }

        $normalCourseIds = self::courseIds($user);
        return ReportBook::query()->where('user_id', $user->id)
            ->whereHas('entries', fn ($q) => $q->where('status', 2))
            ->with(['course' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('id')->get()
            ->filter(fn (ReportBook $book) => self::canViewArchivedBook($user, $book, $normalCourseIds))->values();
    }

    public static function hasArchive(?User $user): bool
    {
        return self::archiveBooks($user)->isNotEmpty();
    }

    public static function canViewArchivedBook(?User $user, ReportBook $book, ?array $normalCourseIds = null): bool
    {
        if (! $user || (int) $book->user_id !== (int) $user->id
            || self::isCoachingCourse((int) $book->course_id)) {
            return false;
        }

        $entries = $book->entries()->where('status', 2);
        if (self::archiveIncludesManualEntries($book, $normalCourseIds)) {
            return $entries->exists();
        }

        // Previously system-approved entries remain downloadable after reactivation.
        return LocationPolicy::schemaReady() && self::onlyPreservedApprovals($entries)->exists();
    }

    public static function archivedEntries(ReportBook $book)
    {
        $query = $book->entries()->where('status', 2)->orderBy('entry_date')->orderBy('id');
        if (! self::archiveIncludesManualEntries($book)) {
            self::onlyPreservedApprovals($query);
        }

        return $query;
    }

    private static function onlyPreservedApprovals($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('automated_approval_audit_id')->orWhereExists(fn ($audit) =>
            $audit->selectRaw('1')->from('report_book_policy_audit as signature_audit')
                ->whereColumn('signature_audit.entry_id', 'report_book_entries.id')
                ->whereColumn('signature_audit.book_id', 'report_book_entries.report_book_id')
                ->where('signature_audit.event', 'manual_signatures_preserved')));
    }

    private static function archiveIncludesManualEntries(ReportBook $book, ?array $normalCourseIds = null): bool
    {
        return LocationPolicy::forBook($book)['mode'] !== 'normal'
            || ! in_array((int) $book->course_id, $normalCourseIds ?? self::courseIds($book->user), true);
    }

    public static function entryUrl(?User $user): string
    {
        return $user && ! self::canUseEditor($user) && self::hasArchive($user)
            ? route('reportbook.archive') : route('reportbook');
    }
}
