<?php

namespace App\Services\ReportBooks;

use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Support\ParticipantReportBookAccess;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Finishes the fixed set of submitted entries attached to waiting review tasks. */
class ApprovalRunner
{
    private const RUNS = 'report_book_approval_runs';

    private const REVISIONS = 'report_book_policy_revisions';

    private const POLICIES = 'report_book_institute_policies';

    private const AUDIT = 'report_book_policy_audit';

    public function runDue(?CarbonInterface $at = null): array
    {
        if (! LocationPolicy::schemaReady()) {
            return [];
        }

        $queryAt = $this->utc($at);
        $ids = DB::table(self::RUNS.' as runs')
            ->join(self::REVISIONS.' as revisions', 'revisions.id', '=', 'runs.revision_id')
            ->whereIn('runs.status', ['pending', 'running', 'failed'])
            ->where('revisions.effective_at', '<=', $queryAt->format('Y-m-d H:i:s'))
            ->orderBy('runs.id')->pluck('runs.id');

        $results = [];
        foreach ($ids as $id) {
            $results[(int) $id] = $this->run((int) $id, $at);
        }

        return $results;
    }

    public function run(int $runId, ?CarbonInterface $at = null): array
    {
        if (! LocationPolicy::schemaReady()) {
            return ['status' => 'unavailable'];
        }

        try {
            // Discover immutable identifiers before entering a transaction. A plain discovery read
            // inside an InnoDB transaction would pin its later snapshot before a policy lock wait.
            $identity = $this->discoverRun($runId);
            $run = $this->prepare($runId, $identity, $at);
            if (! $run || $run->status !== 'running') {
                return ['status' => $run->status ?? 'missing'];
            }

            $snapshot = $this->decode($run->snapshot);
            foreach ($snapshot['books'] ?? [] as $book) {
                if (! $this->approveBook($runId, $identity, $book, $at)) {
                    break;
                }
            }

            return $this->finish($runId, $identity, $at);
        } catch (Throwable $e) {
            $at = $this->utc($at);
            // Preserve the snapshot and already committed books for an idempotent retry.
            DB::transaction(function () use ($runId, $at, $e): void {
                $run = DB::table(self::RUNS)->where('id', $runId)->lockForUpdate()->first();
                if (! $run || in_array($run->status, ['completed', 'superseded'], true)) {
                    return;
                }
                $error = mb_substr($e->getMessage(), 0, 2000);
                DB::table(self::RUNS)->where('id', $runId)->update([
                    'status' => 'failed', 'error' => $error, 'updated_at' => $at->format('Y-m-d H:i:s'),
                ]);
                $this->audit($run, 'run_failed', "run:{$runId}:failed:".hash('sha256', $error), [
                    'message' => $error,
                ], $at);
            }, 3);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function prepare(int $runId, ?array $identity, ?CarbonInterface $at): ?object
    {
        return DB::transaction(function () use ($runId, $identity, $at): ?object {
            [$run, $revision, $policy] = $this->lockRun($runId, $identity);
            $at = $this->utc($at); // Re-evaluate wall time after waiting for the policy lock.
            if (! $run || in_array($run->status, ['completed', 'superseded'], true)) {
                return $run;
            }
            if (CarbonImmutable::parse($revision->effective_at, 'UTC')->greaterThan($at)) {
                return $run;
            }
            if (! $this->isCurrent($revision, $policy, $at)) {
                return $this->supersede($run, $at);
            }

            $this->verifyStamp($revision);
            if ($run->snapshot === null) {
                $snapshot = $this->snapshot($revision, $policy, $at);
                DB::table(self::RUNS)->where('id', $runId)->update([
                    'snapshot' => $this->json($snapshot), 'results' => $this->json(['books' => [], 'excluded' => $snapshot['excluded']]),
                ]);
                $this->audit($run, 'run_snapshot', "run:{$runId}:snapshot", [
                    'effective_at' => $snapshot['effective_at'],
                    'book_count' => count($snapshot['books']),
                    'entry_count' => array_sum(array_map(fn ($book) => count($book['entries']), $snapshot['books'])),
                    'excluded' => $snapshot['excluded'],
                ], $at);
            }

            DB::table(self::RUNS)->where('id', $runId)->update([
                'status' => 'running', 'error' => null,
                'started_at' => $run->started_at ?? $at->format('Y-m-d H:i:s'),
                'updated_at' => $at->format('Y-m-d H:i:s'),
            ]);

            return DB::table(self::RUNS)->where('id', $runId)->first();
        }, 3);
    }

    private function snapshot(object $revision, object $policy, CarbonImmutable $at): array
    {
        // Legacy application timestamps are in app.timezone; policy timestamps are UTC.
        $cutoff = CarbonImmutable::parse($revision->effective_at, 'UTC')
            ->setTimezone(config('app.timezone', 'Europe/Berlin'))->format('Y-m-d H:i:s');
        $bookIds = DB::table('admin_tasks')
            ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)
            ->where('context_type', ReportBook::class)->whereIn('status', [0, 1])
            ->where('created_at', '<=', $cutoff)->orderBy('context_id')->distinct()->pluck('context_id');

        $eligibleBooks = ReportBook::query()->whereIn('id', $bookIds)->where('created_at', '<=', $cutoff)
            ->orderBy('id')->get()->filter(fn ($book) => in_array((int) $policy->institut_id,
                LocationPolicy::forBook($book, $at)['institute_ids'] ?? [], true));
        // Lock the person/enrollment context before the books, matching participant/admin actions.
        foreach ($eligibleBooks as $book) {
            LocationPolicy::forBook($book, $at, true);
        }
        $books = ReportBook::query()->whereIn('id', $eligibleBooks->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get();
        $tasks = DB::table('admin_tasks')->whereIn('context_id', $books->pluck('id'))
            ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)->where('context_type', ReportBook::class)
            ->whereIn('status', [0, 1])->where('created_at', '<=', $cutoff)
            ->orderBy('id')->lockForUpdate()->get()->groupBy('context_id');
        $submittedEntries = DB::table('report_book_entries')->whereIn('report_book_id', $books->pluck('id'))
            ->where('status', 1)->orderBy('id')->lockForUpdate()->get();
        $allSubmittedEntries = $submittedEntries->groupBy('report_book_id');
        $entries = $submittedEntries->filter(fn ($entry) => $entry->created_at !== null && $entry->created_at <= $cutoff
            && ($entry->updated_at === null || $entry->updated_at <= $cutoff)
            && ($entry->submitted_at === null || $entry->submitted_at <= $cutoff)
            && $entry->automated_approval_audit_id === null)->groupBy('report_book_id');

        $candidates = [];
        $excluded = [];
        foreach ($books as $book) {
            $location = LocationPolicy::forBook($book, $at, true);
            $bookTasks = $tasks->get($book->id, collect());
            $bookEntries = $entries->get($book->id, collect());
            $submittedSetChanged = $bookEntries->count() !== $allSubmittedEntries->get($book->id, collect())->count();
            $reason = ! $this->belongsToRevision($location, $revision) ? 'ambiguous_or_changed_location'
                : (ParticipantReportBookAccess::isCoachingCourse((int) $book->course_id) ? 'coaching'
                    : ($bookTasks->isEmpty() ? 'no_waiting_task'
                        : ($submittedSetChanged ? 'submitted_entry_set_changed'
                            : ($bookEntries->isEmpty() ? 'no_eligible_submitted_entry' : null))));
            if ($reason !== null) {
                $excluded[] = ['book_id' => (int) $book->id, 'reason' => $reason];

                continue;
            }

            $candidates[] = [
                'book_id' => (int) $book->id,
                'context' => $this->bookContext($book, $location),
                'task_ids' => $bookTasks->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'tasks' => $bookTasks->map(fn ($task) => ['id' => (int) $task->id,
                    'fingerprint' => $this->fingerprint($task)])->values()->all(),
                'entries' => $bookEntries->map(fn ($entry) => [
                    'id' => (int) $entry->id, 'fingerprint' => $this->fingerprint($entry),
                ])->values()->all(),
            ];
        }

        return ['version' => 1, 'effective_at' => $revision->effective_at, 'books' => $candidates, 'excluded' => $excluded];
    }

    private function approveBook(int $runId, array $identity, array $snapshot, ?CarbonInterface $at): bool
    {
        return DB::transaction(function () use ($runId, $identity, $snapshot, $at): bool {
            [$run, $revision, $policy] = $this->lockRun($runId, $identity);
            $at = $this->utc($at);
            if (! $run || $run->status !== 'running') {
                return false;
            }
            if (! $this->isCurrent($revision, $policy, $at)) {
                $this->supersede($run, $at);

                return false;
            }
            $results = $this->decode($run->results);
            $bookId = (int) $snapshot['book_id'];
            if (isset($results['books'][$bookId])) {
                return true;
            }

            $this->verifyStamp($revision);
            $candidateBook = ReportBook::query()->find($bookId);
            if ($candidateBook) {
                LocationPolicy::forBook($candidateBook, $at, true);
            }
            $book = ReportBook::query()->whereKey($bookId)->lockForUpdate()->first();
            $location = $book ? LocationPolicy::forBook($book, $at, true) : [];
            $tasks = DB::table('admin_tasks')->whereIn('id', $snapshot['task_ids'])
                ->orderBy('id')->lockForUpdate()->get();
            $expectedEntryIds = array_map('intval', array_column($snapshot['entries'], 'id'));
            sort($expectedEntryIds);
            $entries = DB::table('report_book_entries')->where(fn ($query) => $query
                ->where(fn ($submitted) => $submitted->where('report_book_id', $bookId)->where('status', 1))
                ->orWhereIn('id', $expectedEntryIds))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentSubmittedIds = $entries->filter(fn ($entry) => (int) $entry->report_book_id === $bookId && (int) $entry->status === 1)
                ->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();

            $reason = null;
            if (! $book || ! $this->belongsToRevision($location, $revision)
                || $this->bookContext($book, $location) !== ($snapshot['context'] ?? null)
                || ParticipantReportBookAccess::isCoachingCourse((int) $book->course_id)) {
                $reason = 'book_context_changed';
            } elseif ($currentSubmittedIds !== $expectedEntryIds) {
                $reason = 'submitted_entry_set_changed';
            } elseif ($tasks->count() !== count($snapshot['task_ids']) || $tasks->contains(fn ($task) => (int) $task->context_id !== $bookId || $task->context_type !== ReportBook::class
                || $task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW || ! in_array((int) $task->status, [0, 1], true))) {
                $reason = 'review_task_changed';
            } else {
                $expectedTasks = collect($snapshot['tasks'] ?? [])->keyBy('id');
                foreach ($tasks as $task) {
                    if (! isset($expectedTasks[$task->id])
                        || ! hash_equals($expectedTasks[$task->id]['fingerprint'], $this->fingerprint($task))) {
                        $reason = 'review_task_changed';
                        break;
                    }
                }
                foreach ($snapshot['entries'] as $expected) {
                    $entry = $entries->get($expected['id']);
                    if (! $entry || (int) $entry->report_book_id !== $bookId || (int) $entry->status !== 1
                        || $entry->automated_approval_audit_id !== null
                        || ! hash_equals($expected['fingerprint'], $this->fingerprint($entry))) {
                        $reason = 'submitted_entry_changed';
                        break;
                    }
                }
            }

            if ($reason !== null) {
                $results['books'][$bookId] = ['status' => 'skipped', 'reason' => $reason, 'approved_entries' => 0, 'completed_tasks' => 0];
                $this->audit($run, 'book_skipped', "run:{$runId}:book:{$bookId}:skipped", ['reason' => $reason], $at, $bookId);
            } else {
                $localNow = $at->setTimezone(config('app.timezone', 'Europe/Berlin'))->format('Y-m-d H:i:s');
                $invalidatedSignatureIds = data_get($book->settings, 'reportbook_invalidated_signature_ids', []);
                $invalidatedSignatureIds = is_array($invalidatedSignatureIds)
                    ? array_values(array_unique(array_map('intval', $invalidatedSignatureIds))) : [];
                $signatureFiles = DB::table('files')->where('fileable_type', ReportBook::class)->where('fileable_id', $bookId)
                    ->whereIn('type', ['sign_reportbook_participant', 'reportbook_participant', 'sign_reportbook_trainer'])
                    ->whereNotIn('id', $invalidatedSignatureIds)
                    ->orderBy('id')->lockForUpdate()->get()->map(fn ($file) => $this->signatureSnapshot($file))->all();
                foreach ($entries as $entry) {
                    $after = ['status' => 2, 'updated_at' => $localNow];
                    $auditId = $this->audit($run, 'entry_approved', "run:{$runId}:entry:{$entry->id}:approved", [
                        'before' => (array) $entry, 'after' => $after,
                        'stamp' => ['path' => $revision->stamp_path, 'sha256' => $revision->stamp_sha256,
                            'mime' => $revision->stamp_mime, 'revision_id' => (int) $revision->id],
                        'approved_at' => $at->toIso8601String(), 'method' => 'location_policy',
                        'context' => $snapshot['context'] + ['task_ids' => $snapshot['task_ids'], 'signature_files' => $signatureFiles],
                    ], $at, $bookId, (int) $entry->id, null, $revision->created_by);

                    // Query-builder updates intentionally avoid participant submission events and mails.
                    DB::table('report_book_entries')->where('id', $entry->id)->update([
                        'status' => 2, 'automated_approval_audit_id' => $auditId, 'updated_at' => $localNow,
                    ]);
                }
                foreach ($tasks as $task) {
                    DB::table('admin_tasks')->where('id', $task->id)->update([
                        'status' => AdminTask::STATUS_COMPLETED, 'completed_at' => $localNow, 'updated_at' => $localNow,
                    ]);
                    $this->audit($run, 'task_completed', "run:{$runId}:task:{$task->id}:completed", [
                        'before' => (array) $task,
                        'after' => ['status' => AdminTask::STATUS_COMPLETED, 'completed_at' => $localNow],
                        'approved_entry_ids' => $entries->keys()->map(fn ($id) => (int) $id)->all(),
                        'reason' => 'submitted_entries_released_by_location_policy',
                    ], $at, $bookId, null, (int) $task->id, $revision->created_by);
                }
                $results['books'][$bookId] = ['status' => 'approved', 'approved_entries' => $entries->count(), 'completed_tasks' => $tasks->count()];
            }

            DB::table(self::RUNS)->where('id', $runId)->update([
                'results' => $this->json($results), 'updated_at' => $at->format('Y-m-d H:i:s'),
            ]);

            return true;
        }, 3);
    }

    private function finish(int $runId, array $identity, ?CarbonInterface $at): array
    {
        return DB::transaction(function () use ($runId, $identity, $at): array {
            [$run, $revision, $policy] = $this->lockRun($runId, $identity);
            $at = $this->utc($at);
            if (! $run) {
                return ['status' => 'missing'];
            }
            $results = $this->decode($run->results);
            if ($run->status !== 'running') {
                return ['status' => $run->status] + $results;
            }
            if (! $this->isCurrent($revision, $policy, $at)) {
                $this->supersede($run, $at);

                return ['status' => 'superseded'] + $results;
            }
            $snapshot = $this->decode($run->snapshot);
            if (count($results['books'] ?? []) !== count($snapshot['books'] ?? [])) {
                return ['status' => 'running'] + $results;
            }
            $books = array_values($results['books'] ?? []);
            $results['approved_entries'] = array_sum(array_column($books, 'approved_entries'));
            $results['completed_tasks'] = array_sum(array_column($books, 'completed_tasks'));
            $results['skipped_books'] = count(array_filter($books, fn ($book) => $book['status'] === 'skipped'));
            $results['excluded_books'] = count($results['excluded'] ?? []);
            DB::table(self::RUNS)->where('id', $runId)->update([
                'status' => 'completed', 'results' => $this->json($results), 'error' => null,
                'completed_at' => $at->format('Y-m-d H:i:s'), 'updated_at' => $at->format('Y-m-d H:i:s'),
            ]);
            $this->audit($run, 'run_completed', "run:{$runId}:completed", [
                'approved_entries' => $results['approved_entries'], 'completed_tasks' => $results['completed_tasks'],
                'skipped_books' => $results['skipped_books'],
            ], $at);

            return ['status' => 'completed'] + $results;
        }, 3);
    }

    private function discoverRun(int $runId): ?array
    {
        $candidate = DB::table(self::RUNS)->where('id', $runId)->first(['revision_id']);
        if (! $candidate) {
            return null;
        }
        $revision = DB::table(self::REVISIONS)->where('id', $candidate->revision_id)->first(['id', 'policy_id']);
        if (! $revision) {
            throw new RuntimeException('Die Standortrevision des Freigabelaufs fehlt.');
        }

        return ['revision_id' => (int) $revision->id, 'policy_id' => (int) $revision->policy_id];
    }

    /** Lock order: institute policy -> revision/run -> person/enrollment context -> books -> tasks -> entries. */
    private function lockRun(int $runId, ?array $identity): array
    {
        if ($identity === null) {
            return [null, null, null];
        }
        $policy = DB::table(self::POLICIES)->where('id', $identity['policy_id'])->lockForUpdate()->first();
        if (! $policy) {
            throw new RuntimeException('Die Standortregel des Freigabelaufs fehlt.');
        }
        $revision = DB::table(self::REVISIONS)->where('id', $identity['revision_id'])->lockForUpdate()->first();
        $run = DB::table(self::RUNS)->where('id', $runId)->lockForUpdate()->first();
        if (! $revision || (int) $revision->policy_id !== $identity['policy_id']
            || ($run && (int) $run->revision_id !== $identity['revision_id'])) {
            throw new RuntimeException('Die unveränderliche Zuordnung des Freigabelaufs wurde verändert.');
        }

        return [$run, $revision, $policy];
    }

    private function isCurrent(object $revision, object $policy, CarbonImmutable $at): bool
    {
        $current = LocationPolicy::forInstitute((int) $policy->institut_id, $at);

        return $revision->stamp_enabled
            && (int) ($current['revision_id'] ?? 0) === (int) $revision->id
            && $current['stamp_enabled'];
    }

    private function belongsToRevision(array $location, object $revision): bool
    {
        return in_array($location['mode'] ?? null, ['normal', 'archive'], true) && ! ($location['ambiguous'] ?? true)
            && (int) ($location['revision_id'] ?? 0) === (int) $revision->id;
    }

    private function bookContext(ReportBook $book, array $location): array
    {
        return [
            'book_id' => (int) $book->id, 'user_id' => (int) $book->user_id,
            'course_id' => (int) $book->course_id, 'massnahme_id' => $book->massnahme_id,
            'institute_id' => $location['institute_id'] ?? null,
            'person_ids' => $location['person_ids'] ?? [],
        ];
    }

    private function supersede(object $run, CarbonImmutable $at): object
    {
        DB::table(self::RUNS)->where('id', $run->id)->update([
            'status' => 'superseded', 'completed_at' => $at->format('Y-m-d H:i:s'),
            'updated_at' => $at->format('Y-m-d H:i:s'),
        ]);
        $this->audit($run, 'run_superseded', "run:{$run->id}:superseded", [], $at);
        $run->status = 'superseded';

        return $run;
    }

    private function verifyStamp(object $revision): void
    {
        if (! StampStorage::verify((string) $revision->stamp_path, (string) $revision->stamp_sha256, (string) $revision->stamp_mime)) {
            throw new RuntimeException('Das hinterlegte Stempelbild fehlt oder stimmt nicht mit seiner unveränderlichen Prüfsumme überein.');
        }
    }

    private function fingerprint(object $entry): string
    {
        return hash('sha256', $this->json((array) $entry));
    }

    private function signatureSnapshot(object $file): array
    {
        $snapshot = (array) $file;
        $snapshot['disk'] = ($file->disk ?? null) ?: 'private';
        $snapshot['sha256'] = null;
        $snapshot['missing_at_snapshot'] = true;
        if (in_array($snapshot['disk'], ['private', 'public'], true) && ! empty($file->path)
            && Storage::disk($snapshot['disk'])->exists($file->path)) {
            $snapshot['sha256'] = hash('sha256', Storage::disk($snapshot['disk'])->get($file->path));
            $snapshot['missing_at_snapshot'] = false;
        }

        return $snapshot;
    }

    private function audit(object $run, string $event, string $key, array $payload, CarbonImmutable $at,
        ?int $bookId = null, ?int $entryId = null, ?int $taskId = null, ?int $actorId = null): int
    {
        $existing = DB::table(self::AUDIT)->where('event_key', $key)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table(self::AUDIT)->insertGetId([
            'revision_id' => $run->revision_id, 'run_id' => $run->id,
            'event_key' => $key, 'event' => $event, 'book_id' => $bookId,
            'entry_id' => $entryId, 'task_id' => $taskId, 'actor_id' => $actorId,
            'payload' => $this->json($payload), 'created_at' => $at->format('Y-m-d H:i:s'),
        ]);
    }

    private function utc(?CarbonInterface $at): CarbonImmutable
    {
        return $at ? CarbonImmutable::instance($at)->utc() : CarbonImmutable::now('UTC');
    }

    private function decode(?string $value): array
    {
        return $value === null ? [] : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
