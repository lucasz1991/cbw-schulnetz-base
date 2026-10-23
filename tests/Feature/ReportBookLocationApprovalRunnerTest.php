<?php

namespace Tests\Feature;

use App\Jobs\ReportBook\CheckReportBooks;
use App\Jobs\ReportBook\SendMissingReportBookReminderJob;
use App\Models\AdminTask;
use App\Models\ReportBook;
use App\Services\ReportBook\ReportBookPdfContext;
use App\Services\ReportBooks\ApprovalRunner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReportBookLocationApprovalRunnerTest extends TestCase
{
    private CarbonImmutable $instant;

    private string $stampPath;

    private string $stampBytes;

    private string $diskName;

    private bool $failTaskUpdate = false;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'reportbook_approval_testing');
        config()->set('database.connections.reportbook_approval_testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('reportbook_approval_testing');
        DB::reconnect('reportbook_approval_testing');
        config()->set('app.timezone', 'Europe/Berlin');
        $this->instant = CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($this->instant);
        \Illuminate\Support\Carbon::setTestNow($this->instant);
        $this->diskName = 'reportbook_runner_test_'.bin2hex(random_bytes(8));
        Storage::set('private', Storage::fake($this->diskName));
        $this->stampBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aVZkAAAAASUVORK5CYII=');
        $this->stampPath = 'reportbook-stamps/'.hash('sha256', $this->stampBytes).'.png';
        Storage::disk('private')->put($this->stampPath, $this->stampBytes);
        $this->createSchema();
        (require database_path('migrations/2026_10_19_180000_create_report_book_location_policies.php'))->up();
        DB::table('users')->insert(['id' => 1, 'name' => 'Teilnehmer', 'status' => 1]);
        DB::listen(function ($query): void {
            if ($this->failTaskUpdate && str_starts_with(strtolower($query->sql), 'update "admin_tasks"')) {
                $this->failTaskUpdate = false;
                throw new RuntimeException('Simulated task write failure');
            }
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        if (isset($this->diskName)) {
            Storage::disk($this->diskName)->deleteDirectory('reportbook-stamps');
            Storage::disk($this->diskName)->deleteDirectory('signatures');
        }
        DB::disconnect('reportbook_approval_testing');
        parent::tearDown();
    }

    public function test_mixed_books_release_only_submitted_entries_complete_duplicate_tasks_and_keep_provenance(): void
    {
        [$book, $entries, $task] = $this->book([0, 1, 2, 3]);
        $duplicate = $this->task($book, 1);
        $historicalTask = $this->task($book, 2);
        DB::table('files')->insert(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_trainer', 'path' => 'existing-signature.png']);
        $run = $this->revision();
        $result = app(ApprovalRunner::class)->run($run);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['approved_entries']);
        $this->assertSame(2, $result['completed_tasks']);
        $this->assertSame([0, 2, 2, 3], $this->statuses($book));
        $this->assertSame(2, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
        $this->assertSame(2, (int) DB::table('admin_tasks')->where('id', $duplicate)->value('status'));
        $this->assertSame('2026-10-18 10:00:00', DB::table('admin_tasks')->where('id', $historicalTask)->value('completed_at'));
        $this->assertNull(DB::table('report_book_entries')->where('id', $entries[2])->value('automated_approval_audit_id'));
        $auditId = DB::table('report_book_entries')->where('id', $entries[1])->value('automated_approval_audit_id');
        $audit = DB::table('report_book_policy_audit')->where('id', $auditId)->first();
        $payload = json_decode($audit->payload, true);
        $this->assertSame('entry_approved', $audit->event);
        $this->assertSame(1, $payload['before']['status']);
        $this->assertSame(2, $payload['after']['status']);
        $this->assertSame($this->stampPath, $payload['stamp']['path']);
        $this->assertSame(hash('sha256', $this->stampBytes), $payload['stamp']['sha256']);
        $this->assertSame('2026-10-19T12:00:00+00:00', $payload['approved_at']);
        $this->assertSame($book, $payload['context']['book_id']);
        $this->assertSame(1, $payload['context']['institute_id']);
        $this->assertSame([$task, $duplicate], $payload['context']['task_ids']);
        $this->assertSame('existing-signature.png', $payload['context']['signature_files'][0]['path']);
        $this->assertSame('existing-signature.png', DB::table('files')->value('path'));
        $this->assertSame(0, DB::table('messages')->count());
        $this->assertSame(0, DB::table('mails')->count());

        $beforeAudit = DB::table('report_book_policy_audit')->orderBy('id')->get()->toJson();
        $again = app(ApprovalRunner::class)->run($run);
        $this->assertSame('completed', $again['status']);
        $this->assertSame($beforeAudit, DB::table('report_book_policy_audit')->orderBy('id')->get()->toJson());
        $this->assertSame([0, 2, 2, 3], $this->statuses($book));
    }

    public function test_invalidated_signature_files_remain_stored_but_are_not_attributed_to_new_approvals(): void
    {
        [$book] = $this->book([1]);
        $historicalSignature = DB::table('files')->insertGetId(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_participant', 'path' => 'historical-signature.png']);
        $currentSignature = DB::table('files')->insertGetId(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_participant', 'path' => 'current-signature.png']);
        DB::table('report_books')->where('id', $book)->update([
            'settings' => json_encode(['reportbook_invalidated_signature_ids' => [(string) $historicalSignature]]),
        ]);

        $result = app(ApprovalRunner::class)->run($this->revision());
        $audit = DB::table('report_book_policy_audit')->where('book_id', $book)->where('event', 'entry_approved')->first();
        $payload = json_decode($audit->payload, true);
        $this->assertSame('completed', $result['status']);
        $this->assertSame([$currentSignature], array_column($payload['context']['signature_files'], 'id'));
        $this->assertSame('historical-signature.png', DB::table('files')->where('id', $historicalSignature)->value('path'));
        $this->assertSame(2, DB::table('files')->where('fileable_id', $book)->count());
    }

    public function test_only_waiting_tasks_at_the_unambiguous_ordinary_location_are_candidates(): void
    {
        [$eligible] = $this->book([1]);
        [$withoutTask] = $this->book([1], taskStatus: null);
        [$completed] = $this->book([1], taskStatus: 2);
        [$other] = $this->book([1], institute: 2);
        [$coaching] = $this->book([1], type: 'coaching');
        [$ambiguous] = $this->book([1]);
        $course = DB::table('report_books')->where('id', $ambiguous)->value('course_id');
        $person = DB::table('persons')->insertGetId(['user_id' => 1, 'institut_id' => 2]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course, 'person_id' => $person, 'is_active' => 1]);
        $result = app(ApprovalRunner::class)->run($this->revision());

        $this->assertSame(1, $result['approved_entries']);
        $this->assertSame(2, $result['excluded_books']);
        $this->assertSame([2], $this->statuses($eligible));
        foreach ([$withoutTask, $completed, $other, $coaching, $ambiguous] as $book) {
            $this->assertSame([1], $this->statuses($book));
        }
    }

    public function test_system_approval_binds_existing_signature_bytes_and_records_missing_legacy_files(): void
    {
        [$book] = $this->book([1]);
        $existingPath = 'signatures/report-book/'.$book.'/participant.png';
        $missingPath = 'signatures/report-book/'.$book.'/trainer.png';
        Storage::disk('private')->put($existingPath, $this->stampBytes);
        $existingId = DB::table('files')->insertGetId(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_participant', 'path' => $existingPath]);
        $missingId = DB::table('files')->insertGetId(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_trainer', 'path' => $missingPath]);
        $result = app(ApprovalRunner::class)->run($this->revision());
        $audit = DB::table('report_book_policy_audit')->where('book_id', $book)->where('event', 'entry_approved')->first();
        $snapshots = collect(json_decode($audit->payload, true)['context']['signature_files'])->keyBy('id');

        $this->assertSame('completed', $result['status']);
        $this->assertSame('private', $snapshots[$existingId]['disk']);
        $this->assertSame(hash('sha256', $this->stampBytes), $snapshots[$existingId]['sha256']);
        $this->assertFalse($snapshots[$existingId]['missing_at_snapshot']);
        $this->assertTrue($snapshots[$missingId]['missing_at_snapshot']);
        $this->assertNull($snapshots[$missingId]['sha256']);
        $this->assertFalse(Storage::disk('private')->exists($missingPath));

        // A later file at the formerly missing path must never become historical evidence.
        Storage::disk('private')->put($missingPath, $this->stampBytes);
        $model = ReportBook::with('entries')->findOrFail($book);
        app(ReportBookPdfContext::class)->prepare($model);
        $this->assertStringStartsWith('data:image/png;base64,', $model->entries->first()->signature_evidence['participant']['image']);
        $this->assertNull($model->entries->first()->signature_evidence['trainer']['image']);
    }

    public function test_system_signature_hash_rejects_changed_or_missing_historical_image_without_new_writes(): void
    {
        [$book] = $this->book([1]);
        $path = 'signatures/report-book/'.$book.'/participant.png';
        Storage::disk('private')->put($path, $this->stampBytes);
        DB::table('files')->insert(['fileable_id' => $book, 'fileable_type' => ReportBook::class,
            'type' => 'sign_reportbook_participant', 'path' => $path]);
        app(ApprovalRunner::class)->run($this->revision());
        $beforeAudit = DB::table('report_book_policy_audit')->orderBy('id')->get()->toJson();
        $beforeEntries = DB::table('report_book_entries')->orderBy('id')->get()->toJson();

        foreach (['changed', 'missing'] as $condition) {
            if ($condition === 'changed') {
                Storage::disk('private')->put($path, 'changed historical image');
            } else {
                Storage::disk('private')->delete($path);
            }
            try {
                app(ReportBookPdfContext::class)->prepare(ReportBook::with('entries')->findOrFail($book));
                $this->fail('Historical signature '.$condition.' must reject the PDF.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        }
        $this->assertSame($beforeAudit, DB::table('report_book_policy_audit')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeEntries, DB::table('report_book_entries')->orderBy('id')->get()->toJson());
    }

    public function test_due_instant_uses_utc_and_existing_application_timestamps_use_berlin(): void
    {
        [$legacy, $legacyEntries] = $this->book([1]);
        DB::table('report_book_entries')->where('id', $legacyEntries[0])->update(['submitted_at' => null, 'created_at' => '2026-10-19 13:59:59']);
        [$late, $lateEntries] = $this->book([1]);
        DB::table('report_book_entries')->where('id', $lateEntries[0])->update(['submitted_at' => '2026-10-19 14:00:01']);
        [$lateTask, , $task] = $this->book([1]);
        DB::table('admin_tasks')->where('id', $task)->update(['created_at' => '2026-10-19 14:00:01']);
        $run = $this->revision(effective: '2026-10-19 12:00:00');
        $this->assertSame('pending', app(ApprovalRunner::class)->run($run, $this->instant->subSecond())['status']);
        $this->assertNull(DB::table('report_book_approval_runs')->where('id', $run)->value('snapshot'));

        $result = app(ApprovalRunner::class)->run($run, $this->instant);
        $this->assertSame(1, $result['approved_entries']);
        $this->assertSame([2], $this->statuses($legacy));
        $this->assertSame([1], $this->statuses($late));
        $this->assertSame([1], $this->statuses($lateTask));
    }

    public function test_optional_stamp_can_finish_existing_tasks_while_reports_remain_required(): void
    {
        [$book] = $this->book([1, 0]);
        $run = $this->revision(required: true);
        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('completed', $result['status']);
        $this->assertSame([2, 0], $this->statuses($book));
        $this->assertTrue(\App\Services\ReportBooks\LocationPolicy::canWriteBook(ReportBook::findOrFail($book)));
    }

    public function test_delayed_snapshot_does_not_approve_content_changed_after_the_cutoff(): void
    {
        [$book, $entries] = $this->book([1]);
        DB::table('report_book_entries')->where('id', $entries[0])->update(['updated_at' => '2026-10-19 13:00:01']);
        $result = app(ApprovalRunner::class)->run($this->revision());
        $this->assertSame('completed', $result['status']);
        $this->assertSame(0, $result['approved_entries']);
        $this->assertSame(1, $result['excluded_books']);
        $this->assertSame([1], $this->statuses($book));
    }

    public function test_invalid_stamp_fails_without_mutation_and_valid_restoration_can_retry(): void
    {
        [$book] = $this->book([1]);
        $run = $this->revision();
        Storage::disk('private')->put($this->stampPath, 'changed image bytes');
        $this->assertSame('failed', app(ApprovalRunner::class)->run($run)['status']);
        $this->assertSame([1], $this->statuses($book));
        $this->assertSame(0, DB::table('report_book_policy_audit')->where('event', 'entry_approved')->count());
        Storage::disk('private')->put($this->stampPath, $this->stampBytes);
        $this->assertSame('completed', app(ApprovalRunner::class)->run($run)['status']);
        $this->assertSame([2], $this->statuses($book));
    }

    public function test_late_submitted_entry_excludes_the_whole_book_from_the_initial_snapshot(): void
    {
        [$book, $entries, $task] = $this->book([1, 1, 0, 3]);
        DB::table('report_book_entries')->where('id', $entries[1])->update(['submitted_at' => '2026-10-19 13:00:01']);
        $result = app(ApprovalRunner::class)->run($this->revision(required: true));

        $this->assertSame(0, $result['approved_entries']);
        $this->assertSame([['book_id' => $book, 'reason' => 'submitted_entry_set_changed']], $result['excluded']);
        $this->assertSame([1, 1, 0, 3], $this->statuses($book));
        $this->assertSame(0, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
        $this->assertSame(0, DB::table('report_book_policy_audit')->where('event', 'entry_approved')->count());
    }

    public function test_new_submitted_entry_after_snapshot_skips_the_book_and_keeps_its_task_open(): void
    {
        [$book, , $task] = $this->book([1]);
        $run = $this->revision(required: true);
        $this->failTaskUpdate = true;
        app(ApprovalRunner::class)->run($run);
        DB::table('report_book_entries')->insert(['report_book_id' => $book, 'entry_date' => '2026-10-21',
            'text' => 'New submission after snapshot', 'status' => 1, 'submitted_at' => '2026-10-19 14:00:01',
            'created_at' => '2026-10-19 14:00:01', 'updated_at' => '2026-10-19 14:00:01']);

        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('submitted_entry_set_changed', $result['books'][$book]['reason']);
        $this->assertSame([1, 1], $this->statuses($book));
        $this->assertSame(0, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
        $this->assertSame(0, DB::table('report_book_policy_audit')->where('event', 'entry_approved')->count());
    }

    public function test_draft_submitted_after_snapshot_skips_the_book_and_keeps_its_task_open(): void
    {
        [$book, $entries, $task] = $this->book([1, 0]);
        $run = $this->revision(required: true);
        $this->failTaskUpdate = true;
        app(ApprovalRunner::class)->run($run);
        DB::table('report_book_entries')->where('id', $entries[1])->update([
            'status' => 1, 'submitted_at' => '2026-10-19 14:00:01', 'updated_at' => '2026-10-19 14:00:01',
        ]);

        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('submitted_entry_set_changed', $result['books'][$book]['reason']);
        $this->assertSame([1, 1], $this->statuses($book));
        $this->assertSame(0, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
        $this->assertSame(0, DB::table('report_book_policy_audit')->where('event', 'entry_approved')->count());
    }

    public function test_task_failure_rolls_back_entry_and_audit_and_retry_keeps_the_frozen_candidate_set(): void
    {
        [$book] = $this->book([1]);
        $run = $this->revision();
        $this->failTaskUpdate = true;
        $this->assertSame('failed', app(ApprovalRunner::class)->run($run)['status']);
        $this->assertSame([1], $this->statuses($book));
        $this->assertSame(0, DB::table('report_book_policy_audit')->where('event', 'entry_approved')->count());
        $snapshot = DB::table('report_book_approval_runs')->where('id', $run)->value('snapshot');
        $this->assertNotNull($snapshot);
        [$lateBook] = $this->book([1]); // Even deliberately backdated rows cannot join an existing snapshot.

        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['approved_entries']);
        $this->assertSame([2], $this->statuses($book));
        $this->assertSame([1], $this->statuses($lateBook));
        $this->assertSame($snapshot, DB::table('report_book_approval_runs')->where('id', $run)->value('snapshot'));
    }

    public function test_changed_snapshot_entry_skips_the_entire_book_without_task_completion(): void
    {
        [$book, $entries, $task] = $this->book([1, 1]);
        $run = $this->revision();
        $this->failTaskUpdate = true;
        app(ApprovalRunner::class)->run($run);
        DB::table('report_book_entries')->where('id', $entries[1])->update(['text' => 'Changed after snapshot']);

        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['skipped_books']);
        $this->assertSame('submitted_entry_changed', $result['books'][$book]['reason']);
        $this->assertSame([1, 1], $this->statuses($book));
        $this->assertSame(0, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
    }

    public function test_changed_task_assignment_skips_snapshot_instead_of_finishing_another_admins_work(): void
    {
        [$book, , $task] = $this->book([1], taskStatus: 1);
        $run = $this->revision();
        $this->failTaskUpdate = true;
        app(ApprovalRunner::class)->run($run);
        DB::table('admin_tasks')->where('id', $task)->update(['assigned_to' => 42]);
        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('review_task_changed', $result['books'][$book]['reason']);
        $this->assertSame([1], $this->statuses($book));
        $this->assertSame(1, (int) DB::table('admin_tasks')->where('id', $task)->value('status'));
    }

    public function test_changed_book_measure_skips_snapshot_even_when_the_location_stays_the_same(): void
    {
        [$book] = $this->book([1]);
        $run = $this->revision();
        $this->failTaskUpdate = true;
        app(ApprovalRunner::class)->run($run);
        DB::table('report_books')->where('id', $book)->update(['massnahme_id' => 'new-measure']);
        $result = app(ApprovalRunner::class)->run($run);
        $this->assertSame('book_context_changed', $result['books'][$book]['reason']);
        $this->assertSame([1], $this->statuses($book));
    }

    public function test_newer_or_cancelled_revision_supersedes_old_work_and_never_revokes_committed_approvals(): void
    {
        [$book] = $this->book([1]);
        $run = $this->revision();
        app(ApprovalRunner::class)->run($run);
        $auditId = DB::table('report_book_entries')->where('report_book_id', $book)->value('automated_approval_audit_id');
        $second = $this->revision(required: true, stampEnabled: false);
        $this->assertSame('superseded', app(ApprovalRunner::class)->run($second)['status']);
        $this->assertSame('completed', app(ApprovalRunner::class)->run($run)['status']);
        $this->assertSame([2], $this->statuses($book));
        $this->assertSame($auditId, DB::table('report_book_entries')->where('report_book_id', $book)->value('automated_approval_audit_id'));

        [$pendingBook] = $this->book([1]);
        $cancelled = $this->revision();
        $revisionId = DB::table('report_book_approval_runs')->where('id', $cancelled)->value('revision_id');
        DB::table('report_book_policy_audit')->insert(['revision_id' => $revisionId, 'event_key' => 'cancel-test',
            'event' => 'revision_cancelled', 'payload' => '{}', 'created_at' => '2026-10-19 12:00:00']);
        $this->assertSame('superseded', app(ApprovalRunner::class)->run($cancelled)['status']);
        $this->assertSame([1], $this->statuses($pendingBook));
    }

    public function test_queued_checks_and_reminders_recheck_the_current_location_without_creating_work(): void
    {
        [$book, , $task] = $this->book([1]);
        DB::table('admin_tasks')->where('id', $task)->delete();
        $check = new CheckReportBooks([$book]);
        $reminder = new SendMissingReportBookReminderJob([['report_book_id' => $book, 'to_user' => 1, 'course_title' => 'Course', 'open_days' => 1]]);
        $this->revision(stampEnabled: false);
        $check->handle();
        $reminder->handle();

        $this->assertSame(0, DB::table('admin_tasks')->count());
        $this->assertSame(0, DB::table('messages')->count());
        $this->assertNull(ReportBook::findOrFail($book)->getSetting('missing_reportbook_reminder_sent_at'));
        $this->assertSame([1], $this->statuses($book));
    }

    public function test_dispatchers_and_normal_reminders_preserve_unaffected_locations(): void
    {
        [$blocked] = $this->book([1]);
        [$normal] = $this->book([1], institute: 2);
        $this->revision(stampEnabled: false);
        Queue::fake();
        Artisan::call('reportbooks:dispatch-complete-check');
        Queue::assertPushed(CheckReportBooks::class, fn ($job) => $job->ids === [$normal]);
        Queue::assertPushed(CheckReportBooks::class, 1);

        [$blockedDraft] = $this->book([0]);
        [$normalDraft] = $this->book([0], institute: 2);
        DB::table('settings')->insert(['type' => 'mails', 'key' => 'reminder_missing_report_book', 'value' => 'true']);
        Artisan::call('reportbooks:dispatch-missing-reminders');
        Queue::assertPushed(SendMissingReportBookReminderJob::class, function ($job) use ($normalDraft) {
            return array_column($job->reminders, 'report_book_id') === [$normalDraft];
        });
        Queue::assertPushed(SendMissingReportBookReminderJob::class, 1);

        $job = new SendMissingReportBookReminderJob([['report_book_id' => $normalDraft, 'to_user' => 1,
            'course_title' => 'Normal', 'open_days' => 1, 'total_days' => 1]]);
        $job->handle();
        $job->handle();
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertNotNull(ReportBook::findOrFail($normalDraft)->getSetting('missing_reportbook_reminder_sent_at'));
        $this->assertNull(ReportBook::findOrFail($blockedDraft)->getSetting('missing_reportbook_reminder_sent_at'));
    }

    private function revision(bool $required = false, bool $stampEnabled = true, string $effective = '2026-10-19 11:00:00'): int
    {
        $policy = DB::table('report_book_institute_policies')->where('institut_id', 1)->first();
        $policyId = $policy?->id ?? DB::table('report_book_institute_policies')->insertGetId(['institut_id' => 1, 'version' => 0]);
        $version = (int) DB::table('report_book_policy_revisions')->where('policy_id', $policyId)->max('revision') + 1;
        $revision = DB::table('report_book_policy_revisions')->insertGetId([
            'policy_id' => $policyId, 'revision' => $version, 'required' => $required, 'stamp_enabled' => $stampEnabled,
            'stamp_path' => $this->stampPath, 'stamp_sha256' => hash('sha256', $this->stampBytes), 'stamp_mime' => 'image/png',
            'effective_at' => $effective, 'created_by' => 1, 'created_at' => '2026-10-19 10:00:00',
        ]);

        return DB::table('report_book_approval_runs')->insertGetId(['revision_id' => $revision, 'status' => 'pending']);
    }

    private function book(array $statuses, int $institute = 1, ?int $taskStatus = 0, ?string $type = null): array
    {
        $course = DB::table('courses')->insertGetId(['title' => 'Course', 'type' => $type, 'planned_end_date' => '2026-10-01']);
        $person = DB::table('persons')->insertGetId(['user_id' => 1, 'institut_id' => $institute]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course, 'person_id' => $person, 'is_active' => 1]);
        $book = DB::table('report_books')->insertGetId(['user_id' => 1, 'course_id' => $course, 'created_at' => '2026-10-18 10:00:00']);
        $entries = [];
        foreach ($statuses as $index => $status) {
            $date = '2026-10-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $day = DB::table('course_days')->insertGetId(['course_id' => $course, 'date' => $date]);
            $entries[] = DB::table('report_book_entries')->insertGetId([
                'report_book_id' => $book, 'course_day_id' => $day, 'entry_date' => $date,
                'text' => 'Entry '.$index, 'status' => $status, 'submitted_at' => $status === 0 ? null : '2026-10-18 10:00:00',
                'created_at' => '2026-10-18 10:00:00', 'updated_at' => '2026-10-18 10:00:00',
            ]);
        }

        return [$book, $entries, $taskStatus === null ? null : $this->task($book, $taskStatus)];
    }

    private function task(int $book, int $status): int
    {
        return DB::table('admin_tasks')->insertGetId([
            'created_by' => 1, 'context_type' => ReportBook::class, 'context_id' => $book,
            'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW, 'status' => $status, 'assigned_to' => $status === 0 ? null : 1,
            'completed_at' => $status === 2 ? '2026-10-18 10:00:00' : null,
            'created_at' => '2026-10-18 10:00:00', 'updated_at' => '2026-10-18 10:00:00',
        ]);
    }

    private function statuses(int $book): array
    {
        return DB::table('report_book_entries')->where('report_book_id', $book)->orderBy('id')->pluck('status')->map(fn ($s) => (int) $s)->all();
    }

    private function createSchema(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->integer('status');
            $t->timestamps();
        });
        $schema->create('persons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->integer('institut_id');
            $t->text('programdata')->nullable();
            $t->text('statusdata')->nullable();
            $t->softDeletes();
        });
        $schema->create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('type')->nullable();
            $t->string('vtz')->nullable();
            $t->date('planned_end_date')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        $schema->create('course_participant_enrollments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id');
            $t->unsignedBigInteger('person_id');
            $t->boolean('is_active');
            $t->text('source_snapshot')->nullable();
            $t->softDeletes();
        });
        $schema->create('course_days', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id');
            $t->date('date');
            $t->softDeletes();
            $t->timestamps();
        });
        $schema->create('report_books', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('course_id');
            $t->string('massnahme_id')->nullable();
            $t->string('title')->nullable();
            $t->text('settings')->nullable();
            $t->timestamps();
        });
        $schema->create('report_book_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('report_book_id');
            $t->unsignedBigInteger('course_day_id')->nullable();
            $t->date('entry_date');
            $t->text('text')->nullable();
            $t->unsignedTinyInteger('status');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });
        $schema->create('admin_tasks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('created_by');
            $t->nullableMorphs('context');
            $t->string('task_type');
            $t->text('description')->nullable();
            $t->unsignedTinyInteger('status');
            $t->integer('priority')->default(2);
            $t->unsignedBigInteger('assigned_to')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        $schema->create('files', function (Blueprint $t) {
            $t->id();
            $t->morphs('fileable');
            $t->string('type');
            $t->string('path');
        });
        $schema->create('messages', function (Blueprint $t) {
            $t->id();
            $t->string('subject');
            $t->text('message');
            $t->integer('from_user');
            $t->integer('to_user');
            $t->integer('status');
            $t->timestamps();
        });
        $schema->create('mails', fn (Blueprint $t) => $t->id());
        $schema->create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }
}
