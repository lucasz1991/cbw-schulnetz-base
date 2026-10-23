<?php

namespace App\Services\ReportBooks;

use App\Models\ReportBook;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Shared Base/Admin contract. All policy timestamps are UTC; reads never publish or mutate. */
class LocationPolicy
{
    public const NOTICE = 'Für Ihren Standort ist derzeit keine Einreichung von Berichtsheften erforderlich.';

    public static function utcNow(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public static function schemaReady(): bool
    {
        return Schema::hasTable('report_book_institute_policies')
            && Schema::hasTable('report_book_policy_revisions')
            && Schema::hasTable('report_book_approval_runs')
            && Schema::hasTable('report_book_policy_audit')
            && Schema::hasColumn('report_book_entries', 'automated_approval_audit_id');
    }

    public static function forInstitute(int $instituteId, ?CarbonInterface $at = null): array
    {
        $defaults = ['institut_id' => $instituteId, 'required' => true, 'stamp_enabled' => false,
            'revision_id' => null, 'stamp_path' => null, 'stamp_sha256' => null, 'stamp_mime' => null, 'effective_at' => null];
        if (! self::schemaReady()) {
            return $defaults;
        }
        $policyQuery = DB::table('report_book_institute_policies')->where('institut_id', $instituteId);
        if (DB::transactionLevel() > 0) {
            $policyQuery->lockForUpdate();
        }
        $policyId = $policyQuery->value('id');
        if (! $policyId) {
            return $defaults;
        }
        $query = self::revisions((int) $policyId)
            ->where('r.effective_at', '<=', self::instant($at)->format('Y-m-d H:i:s'))
            ->orderByDesc('r.effective_at')->orderByDesc('r.id');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $revision = $query->first();
        return $revision ? self::revisionData($revision) + ['institut_id' => $instituteId] : $defaults;
    }

    public static function forBook(ReportBook $book, ?CarbonInterface $at = null, bool $currentRead = false): array
    {
        return self::resolve((int) $book->user_id, (int) $book->course_id, $book->massnahme_id, false, $at, $currentRead);
    }

    public static function forCourse(User $user, int $courseId, ?string $massnahmeId = null): array
    {
        return self::resolve((int) $user->id, $courseId, $massnahmeId, true);
    }

    public static function canWriteBook(ReportBook $book): bool
    {
        return self::forBook($book, null, DB::transactionLevel() > 0)['mode'] === 'normal';
    }

    /** Call within the caller's transaction, before locking books, tasks and entries. */
    public static function lockForBook(ReportBook $book): array
    {
        $context = self::forBook($book);
        if (self::schemaReady() && $context['institute_ids']) {
            DB::table('report_book_institute_policies')->whereIn('institut_id', $context['institute_ids'])
                ->orderBy('id')->lockForUpdate()->get();
        }
        return self::forBook($book, null, DB::transactionLevel() > 0);
    }

    private static function resolve(int $userId, int $courseId, ?string $massnahmeId, bool $activeOnly, ?CarbonInterface $at = null, bool $currentRead = false): array
    {
        $normal = ['mode' => 'normal', 'required' => true, 'ambiguous' => false, 'institute_id' => null,
            'institute_ids' => [], 'person_ids' => [], 'revision_id' => null, 'stamp_enabled' => false];
        // Additive deployment: before Base migration there is no configured restriction.
        if (! self::schemaReady()) {
            return $normal;
        }
        $query = DB::table('persons as p')->join('course_participant_enrollments as e', 'e.person_id', '=', 'p.id')
            ->where('p.user_id', $userId)->where('e.course_id', $courseId)
            ->select(['p.id', 'p.institut_id', 'p.programdata', 'p.statusdata', 'e.source_snapshot']);
        if ($activeOnly) {
            $query->whereNull('p.deleted_at')->whereNull('e.deleted_at')->where('e.is_active', 1);
        }
        if ($currentRead && DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $invalidContext = false;
        $people = $query->get()->filter(function ($person) use ($massnahmeId, &$invalidContext) {
            if ($massnahmeId === null || $massnahmeId === '') {
                return true;
            }
            $known = [];
            foreach (['programdata', 'statusdata', 'source_snapshot'] as $column) {
                $data = json_decode($person->$column ?? '{}', true);
                if (! is_array($data)) {
                    $invalidContext = true;
                    continue;
                }
                self::collectMeasures($data, $known);
            }
            return $known === [] || in_array((string) $massnahmeId, $known, true);
        });
        $ids = $people->pluck('institut_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->sort()->values()->all();
        $unknown = $invalidContext || $people->isEmpty() || $people->contains(fn ($p) => (int) $p->institut_id < 1);
        $rules = array_map(fn ($id) => self::forInstitute($id, $at), $ids);
        $requirements = array_unique(array_column($rules, 'required'), SORT_REGULAR);
        $conflict = $unknown || count($requirements) > 1;
        $required = ! $conflict && ($rules[0]['required'] ?? true);
        return array_replace($normal, [
            'mode' => $conflict ? 'conflict' : ($required ? 'normal' : 'archive'),
            'required' => $required, 'ambiguous' => $unknown || count($ids) !== 1,
            'institute_id' => count($ids) === 1 && ! $unknown ? $ids[0] : null,
            'institute_ids' => $ids, 'person_ids' => $people->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'revision_id' => count($ids) === 1 ? ($rules[0]['revision_id'] ?? null) : null,
            'stamp_enabled' => count($ids) === 1 && ! $unknown && ($rules[0]['stamp_enabled'] ?? false),
        ]);
    }

    private static function collectMeasures(array $data, array &$ids): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, ['massnahme_id', 'massnahmen_id'], true) && is_scalar($value) && (string) $value !== '') {
                $ids[] = (string) $value;
            } elseif (is_array($value)) {
                self::collectMeasures($value, $ids);
            }
        }
    }

    public static function schedule(int $instituteId, array $input, int $actorId, int $expectedVersion): array
    {
        if (! self::schemaReady()) {
            throw ValidationException::withMessages(['publication_date' => 'Die Base-Datenbankmigration muss zuerst installiert werden.']);
        }
        $data = Validator::make($input + ['institut_id' => $instituteId], [
            'institut_id' => ['required', 'integer', 'min:1'], 'required' => ['required', 'boolean'],
            'stamp_enabled' => ['required', 'boolean'], 'publication_date' => ['required', 'date_format:Y-m-d'],
            'stamp_path' => ['nullable', 'string', 'max:255'], 'stamp_sha256' => ['nullable', 'string', 'size:64'],
            'stamp_mime' => ['nullable', 'in:image/png,image/jpeg'],
        ])->validate();
        $today = self::utcNow()->setTimezone('Europe/Berlin')->startOfDay();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $data['publication_date'], 'Europe/Berlin');
        if ($day->lt($today)) {
            throw ValidationException::withMessages(['publication_date' => 'Eine Veröffentlichung in der Vergangenheit ist nicht möglich.']);
        }
        if ($data['stamp_enabled']) {
            if (! StampStorage::verify($data['stamp_path'] ?? '', $data['stamp_sha256'] ?? '', $data['stamp_mime'] ?? '')) {
                throw ValidationException::withMessages(['stamp_path' => 'Bitte ein gültiges, unverändertes PNG- oder JPEG-Stempelbild hochladen.']);
            }
        }
        return DB::transaction(function () use ($instituteId, $data, $actorId, $expectedVersion, $day) {
            $now = self::utcNow()->format('Y-m-d H:i:s');
            DB::table('report_book_institute_policies')->insertOrIgnore([
                'institut_id' => $instituteId, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $policy = DB::table('report_book_institute_policies')->where('institut_id', $instituteId)->lockForUpdate()->first();
            self::assertVersion($policy, $expectedVersion);
            // A lock wait may cross midnight or an existing publication boundary.
            $instant = self::utcNow();
            $now = $instant->format('Y-m-d H:i:s');
            $today = $instant->setTimezone('Europe/Berlin')->startOfDay();
            if ($day->lt($today)) {
                throw ValidationException::withMessages(['publication_date' => 'Der Veröffentlichungstag ist inzwischen vergangen. Bitte das Datum erneut wählen.']);
            }
            $effective = $day->isSameDay($today) ? $instant : $day->utc();
            $current = self::forInstitute($instituteId, $instant);
            $scheduled = self::revisions((int) $policy->id)->where('r.effective_at', '>', $now)->orderByDesc('r.id')->first();
            $reference = $scheduled ? self::revisionData($scheduled) : $current;
            $same = (bool) $data['required'] === $reference['required'] && (bool) $data['stamp_enabled'] === $reference['stamp_enabled'];
            foreach (['stamp_path', 'stamp_sha256', 'stamp_mime'] as $field) {
                $same = $same && ($data[$field] ?? null) === ($reference[$field] ?? null);
            }
            $sameDate = ! $scheduled || CarbonImmutable::parse($scheduled->effective_at, 'UTC')->setTimezone('Europe/Berlin')->format('Y-m-d') === $data['publication_date'];
            if ($same && $sameDate) {
                return ['revision_id' => $reference['revision_id'], 'version' => (int) $policy->version, 'unchanged' => true];
            }
            foreach (self::revisions((int) $policy->id)->where('r.effective_at', '>', $now)->get() as $old) {
                self::cancelRevision($old, $actorId, 'replaced');
            }
            $version = (int) $policy->version + 1;
            $revisionId = DB::table('report_book_policy_revisions')->insertGetId([
                'policy_id' => $policy->id, 'revision' => $version, 'required' => (bool) $data['required'],
                'stamp_enabled' => (bool) $data['stamp_enabled'], 'stamp_path' => $data['stamp_path'] ?? null,
                'stamp_sha256' => $data['stamp_sha256'] ?? null, 'stamp_mime' => $data['stamp_mime'] ?? null,
                'effective_at' => $effective->format('Y-m-d H:i:s'), 'created_by' => $actorId, 'created_at' => $now,
            ]);
            $runRequested = (bool) $data['stamp_enabled'] && (! $current['stamp_enabled'] || ($current['required'] && ! $data['required']));
            if ($runRequested) {
                DB::table('report_book_approval_runs')->insert(['revision_id' => $revisionId, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('report_book_institute_policies')->where('id', $policy->id)->update(['version' => $version, 'updated_at' => $now]);
            self::audit('revision_scheduled', 'revision:'.$revisionId.':scheduled', $revisionId, $actorId, [
                'institut_id' => $instituteId, 'before' => $current, 'after' => $data,
                'effective_at' => $effective->toIso8601String(), 'run_requested' => $runRequested,
            ]);
            return ['revision_id' => $revisionId, 'version' => $version, 'unchanged' => false];
        }, 3);
    }

    public static function cancel(int $instituteId, int $actorId, int $expectedVersion): void
    {
        DB::transaction(function () use ($instituteId, $actorId, $expectedVersion) {
            $policy = DB::table('report_book_institute_policies')->where('institut_id', $instituteId)->lockForUpdate()->first();
            self::assertVersion($policy, $expectedVersion);
            $future = self::revisions((int) $policy->id)->where('r.effective_at', '>', self::utcNow()->format('Y-m-d H:i:s'))->get();
            foreach ($future as $revision) {
                self::cancelRevision($revision, $actorId, 'cancelled_by_admin');
            }
            if ($future->isNotEmpty()) {
                DB::table('report_book_institute_policies')->where('id', $policy->id)->update([
                    'version' => (int) $policy->version + 1, 'updated_at' => self::utcNow()->format('Y-m-d H:i:s'),
                ]);
            }
        }, 3);
    }

    public static function currentState(int $instituteId): array
    {
        if (! self::schemaReady()) {
            return ['version' => 0, 'current' => self::forInstitute($instituteId), 'scheduled' => null, 'revisions' => [], 'runs' => []];
        }
        // Form values and their optimistic version must describe the same committed state.
        return DB::transaction(function () use ($instituteId) {
            $policy = DB::table('report_book_institute_policies')->where('institut_id', $instituteId)->lockForUpdate()->first();
            $state = ['version' => 0, 'current' => self::forInstitute($instituteId), 'scheduled' => null, 'revisions' => [], 'runs' => []];
            if (! $policy) {
                return $state;
            }
            $future = self::revisions((int) $policy->id)->where('r.effective_at', '>', self::utcNow()->format('Y-m-d H:i:s'))
                ->orderBy('r.effective_at')->lockForUpdate()->first();
            $history = DB::table('report_book_policy_revisions')->where('policy_id', $policy->id)->orderByDesc('id')->lockForUpdate()->get();
            $cancelled = DB::table('report_book_policy_audit')->whereIn('revision_id', $history->pluck('id'))
                ->where('event', 'revision_cancelled')->lockForUpdate()->pluck('revision_id')->all();
            return array_replace($state, [
                'version' => (int) $policy->version, 'scheduled' => $future ? self::revisionData($future) : null,
                'revisions' => $history->map(fn ($r) => self::revisionData($r) + ['cancelled' => in_array($r->id, $cancelled)])->all(),
                'runs' => DB::table('report_book_approval_runs')->whereIn('revision_id', $history->pluck('id'))
                    ->select(['id', 'revision_id', 'status', 'results', 'error', 'started_at', 'completed_at', 'created_at', 'updated_at'])
                    ->orderByDesc('id')->lockForUpdate()->get()
                    ->map(function ($run) { $row = (array) $run; $row['results'] = json_decode($run->results ?? '{}', true); return $row; })->all(),
            ]);
        }, 3);
    }

    public static function preview(int $instituteId): array
    {
        $result = ['participants' => 0, 'books' => 0, 'submitted_entries' => 0, 'tasks' => 0,
            'eligible_books' => 0, 'eligible_entries' => 0, 'eligible_tasks' => 0, 'excluded_count' => 0, 'excluded' => []];
        $users = [];
        $books = ReportBook::query()->whereIn('user_id', DB::table('persons')->where('institut_id', $instituteId)->select('user_id'))->get();
        foreach ($books as $book) {
            $context = self::forBook($book);
            if (! in_array($instituteId, $context['institute_ids'], true)) {
                continue;
            }
            $result['books']++;
            $users[(int) $book->user_id] = true;
            $entries = $book->entries()->where('status', 1)->count();
            $tasks = DB::table('admin_tasks')->where('context_type', $book->getMorphClass())->where('context_id', $book->id)
                ->where('task_type', 'reportbook_review')->whereIn('status', [0, 1])->count();
            $result['submitted_entries'] += $entries;
            $result['tasks'] += $tasks;
            $reason = $context['ambiguous'] ? 'Standort nicht eindeutig' : (self::isCoaching((int) $book->course_id) ? 'Einzelcoaching' : ($tasks === 0 ? 'Keine wartende Prüfaufgabe' : ($entries === 0 ? 'Keine eingereichten Einträge' : null)));
            if ($reason) {
                $result['excluded'][] = ['book_id' => $book->id, 'reason' => $reason];
            } else {
                $result['eligible_books']++;
                $result['eligible_entries'] += $entries;
                $result['eligible_tasks'] += $tasks;
            }
        }
        $result['participants'] = count($users);
        $result['excluded_count'] = count($result['excluded']);
        return $result;
    }

    private static function isCoaching(int $courseId): bool
    {
        $course = DB::table('courses')->where('id', $courseId)->first();
        return ($course->type ?? null) === 'coaching' || ($course->vtz ?? null) === 'E'
            || (Schema::hasTable('coaching_contracts') && DB::table('coaching_contracts')->where('course_id', $courseId)->exists());
    }

    private static function assertVersion(?object $policy, int $expected): void
    {
        if (! $policy || (int) $policy->version !== $expected) {
            throw ValidationException::withMessages(['publication_date' => 'Die Einstellungen wurden zwischenzeitlich geändert. Bitte den Standort neu laden und erneut prüfen.']);
        }
    }

    private static function cancelRevision(object $revision, int $actorId, string $reason): void
    {
        self::audit('revision_cancelled', 'revision:'.$revision->id.':cancelled', (int) $revision->id, $actorId, ['reason' => $reason]);
        DB::table('report_book_approval_runs')->where('revision_id', $revision->id)->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'superseded', 'completed_at' => self::utcNow()->format('Y-m-d H:i:s'), 'updated_at' => self::utcNow()->format('Y-m-d H:i:s')]);
    }

    private static function audit(string $event, string $key, int $revisionId, int $actorId, array $payload): void
    {
        DB::table('report_book_policy_audit')->insert([
            'revision_id' => $revisionId, 'event_key' => $key, 'event' => $event, 'actor_id' => $actorId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => self::utcNow()->format('Y-m-d H:i:s'),
        ]);
    }

    private static function revisions(int $policyId)
    {
        $query = DB::table('report_book_policy_revisions as r')->select('r.*')->where('r.policy_id', $policyId)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('report_book_policy_audit as a')
                    ->whereColumn('a.revision_id', 'r.id')->where('a.event', 'revision_cancelled');
                // InnoDB does not propagate an outer locking read into this subquery.
                if (DB::transactionLevel() > 0) {
                    $q->lockForUpdate();
                }
            });
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        return $query;
    }

    private static function revisionData(object $revision): array
    {
        return ['revision_id' => (int) $revision->id, 'revision' => (int) $revision->revision,
            'required' => (bool) $revision->required, 'stamp_enabled' => (bool) $revision->stamp_enabled,
            'stamp_path' => $revision->stamp_path, 'stamp_sha256' => $revision->stamp_sha256, 'stamp_mime' => $revision->stamp_mime,
            'effective_at' => CarbonImmutable::parse($revision->effective_at, 'UTC')->toIso8601String(),
            'created_by' => $revision->created_by, 'created_at' => CarbonImmutable::parse($revision->created_at, 'UTC')->toIso8601String()];
    }

    private static function instant(?CarbonInterface $at): CarbonImmutable
    {
        return $at ? CarbonImmutable::instance($at)->utc() : self::utcNow();
    }
}
