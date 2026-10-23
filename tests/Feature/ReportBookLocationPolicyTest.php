<?php

namespace Tests\Feature;

use App\Models\ReportBook;
use App\Models\User;
use App\Services\ReportBooks\LocationPolicy;
use App\Services\ReportBooks\StampStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Queue, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportBookLocationPolicyTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 10:00:00', 'UTC'));
        Queue::fake(); Http::preventStrayRequests(); Storage::fake('private');
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->integer('institut_id')->nullable();
            $t->json('programdata')->nullable(); $t->json('statusdata')->nullable(); $t->softDeletes();
        });
        Schema::create('courses', function (Blueprint $t) { $t->id(); $t->string('type')->nullable(); $t->string('vtz')->nullable(); });
        Schema::create('course_participant_enrollments', function (Blueprint $t) {
            $t->id(); $t->integer('course_id'); $t->integer('person_id'); $t->boolean('is_active')->default(true);
            $t->json('source_snapshot')->nullable(); $t->softDeletes();
        });
        Schema::create('report_books', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('course_id'); $t->string('massnahme_id')->nullable(); $t->timestamps();
        });
        Schema::create('report_book_entries', function (Blueprint $t) {
            $t->id(); $t->integer('report_book_id'); $t->integer('status')->default(0); $t->timestamps();
        });
        Schema::create('admin_tasks', function (Blueprint $t) {
            $t->id(); $t->string('context_type'); $t->integer('context_id'); $t->string('task_type'); $t->integer('status');
        });
        (require database_path('migrations/2026_10_19_180000_create_report_book_location_policies.php'))->up();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_unconfigured_location_is_required_and_read_is_non_mutating(): void
    {
        self::assertTrue(LocationPolicy::forInstitute(1)['required']);
        self::assertSame(0, LocationPolicy::currentState(1)['version']);
        self::assertSame(0, DB::table('report_book_institute_policies')->count());
    }

    public function test_future_rule_uses_berlin_midnight_after_dst_without_waiting_for_scheduler(): void
    {
        $revision = $this->schedule(false, false, '2026-10-26');
        self::assertSame('2026-10-25 23:00:00', DB::table('report_book_policy_revisions')->value('effective_at'));
        self::assertTrue(LocationPolicy::forInstitute(1, CarbonImmutable::parse('2026-10-25 22:59:59', 'UTC'))['required']);
        self::assertFalse(LocationPolicy::forInstitute(1, CarbonImmutable::parse('2026-10-25 23:00:00', 'UTC'))['required']);
        self::assertSame($revision['revision_id'], LocationPolicy::currentState(1)['scheduled']['revision_id']);
        self::assertSame(0, DB::table('report_book_approval_runs')->count());
    }

    public function test_today_applies_now_and_identical_save_does_not_make_another_revision(): void
    {
        $first = $this->schedule(false);
        self::assertFalse(LocationPolicy::forInstitute(1)['required']);
        $second = $this->schedule(false, false, '2026-10-19', 1);
        self::assertTrue($second['unchanged']);
        self::assertSame($first['revision_id'], $second['revision_id']);
        self::assertSame(1, DB::table('report_book_policy_revisions')->count());
    }

    public function test_backdating_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->schedule(false, false, '2026-10-18');
    }

    public function test_stale_form_cannot_override_a_newer_plan(): void
    {
        $this->schedule(false, false, '2026-10-26');
        try {
            $this->schedule(false, false, '2026-10-27', 0);
            self::fail('A stale form was accepted.');
        } catch (ValidationException) {
            self::assertSame(1, DB::table('report_book_policy_revisions')->count());
            self::assertSame(1, LocationPolicy::currentState(1)['version']);
        }
    }

    public function test_replacement_and_cancellation_keep_immutable_history(): void
    {
        $first = $this->schedule(false, false, '2026-10-26');
        $original = (array) DB::table('report_book_policy_revisions')->first();
        $second = $this->schedule(false, false, '2026-10-27', 1);
        self::assertSame($original, (array) DB::table('report_book_policy_revisions')->where('id', $first['revision_id'])->first());
        self::assertTrue(LocationPolicy::forInstitute(1, CarbonImmutable::parse('2026-10-26 15:00', 'UTC'))['required']);
        self::assertFalse(LocationPolicy::forInstitute(1, CarbonImmutable::parse('2026-10-27 15:00', 'UTC'))['required']);
        LocationPolicy::cancel(1, 7, $second['version']);
        self::assertNull(LocationPolicy::currentState(1)['scheduled']);
        self::assertTrue(LocationPolicy::forInstitute(1, CarbonImmutable::parse('2026-10-28 15:00', 'UTC'))['required']);
        self::assertSame(2, DB::table('report_book_policy_revisions')->count());
        self::assertSame(2, DB::table('report_book_policy_audit')->where('event', 'revision_cancelled')->count());
    }

    public function test_stamp_is_mandatory_and_hash_is_checked_again_on_schedule(): void
    {
        try {
            $this->schedule(false, true);
            self::fail('Stamp-free release was accepted.');
        } catch (ValidationException) {
            self::assertSame(0, DB::table('report_book_policy_revisions')->count());
        }
        $stamp = StampStorage::store(UploadedFile::fake()->image('stamp.png', 50, 20));
        Storage::disk('private')->put($stamp['stamp_path'], 'replaced image');
        $this->expectException(ValidationException::class);
        $this->schedule(false, true, '2026-10-19', 0, $stamp);
    }

    public function test_stamp_enable_creates_one_run_but_image_change_and_reactivation_do_not(): void
    {
        $stamp = StampStorage::store(UploadedFile::fake()->image('stamp.png', 50, 20));
        $first = $this->schedule(false, true, '2026-10-19', 0, $stamp);
        $same = $this->schedule(false, true, '2026-10-19', $first['version'], $stamp);
        self::assertTrue($same['unchanged']);
        $newStamp = StampStorage::store(UploadedFile::fake()->image('new.png', 80, 20));
        $changed = $this->schedule(false, true, '2026-10-19', $same['version'], $newStamp);
        $this->schedule(true, true, '2026-10-19', $changed['version'], $newStamp);
        self::assertTrue(LocationPolicy::forInstitute(1)['required']);
        self::assertSame(1, DB::table('report_book_approval_runs')->count());
        self::assertTrue(StampStorage::verify($stamp['stamp_path'], $stamp['stamp_sha256'], $stamp['stamp_mime']));
    }

    public function test_independent_enable_with_required_true_is_a_one_time_run(): void
    {
        $stamp = StampStorage::store(UploadedFile::fake()->image('stamp.jpg'));
        $this->schedule(true, true, '2026-10-19', 0, $stamp);
        self::assertTrue(LocationPolicy::forInstitute(1)['required']);
        self::assertSame(1, DB::table('report_book_approval_runs')->count());
    }

    public function test_disabled_stamp_does_not_block_reactivation_when_old_image_is_missing(): void
    {
        $stamp = StampStorage::store(UploadedFile::fake()->image('stamp.png'));
        $this->schedule(false, true, '2026-10-19', 0, $stamp);
        Storage::disk('private')->delete($stamp['stamp_path']);
        $this->schedule(true, false, '2026-10-19', 1, $stamp);
        self::assertTrue(LocationPolicy::forInstitute(1)['required']);
        self::assertFalse(LocationPolicy::forInstitute(1)['stamp_enabled']);
    }

    public function test_lock_wait_crossing_publication_does_not_cancel_an_already_effective_revision(): void
    {
        $first = $this->schedule(false, false, '2026-10-26');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 22:59:59', 'UTC'));
        $changedClock = false;
        DB::listen(function ($query) use (&$changedClock) {
            if (! $changedClock && str_starts_with($query->sql, 'select * from "report_book_institute_policies"')) {
                $changedClock = true;
                CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 23:00:01', 'UTC'));
            }
        });
        $result = $this->schedule(false, false, '2026-10-27', 1);
        self::assertTrue($changedClock);
        self::assertTrue($result['unchanged']);
        self::assertSame($first['revision_id'], LocationPolicy::forInstitute(1)['revision_id']);
        self::assertSame(0, DB::table('report_book_policy_audit')->where('event', 'revision_cancelled')->count());
    }

    public function test_shared_policy_source_matches_admin_copy(): void
    {
        self::assertSame(file_get_contents(app_path('Services/ReportBooks/LocationPolicy.php')),
            file_get_contents(dirname(base_path()).'/admin/app/Services/ReportBooks/LocationPolicy.php'));
    }

    public function test_shared_feri_course_with_conflicting_locations_is_read_only(): void
    {
        $book = $this->book();
        $this->person(2, 2, 1, $book->course_id);
        $this->schedule(false);
        $context = LocationPolicy::forBook($book);
        self::assertSame('conflict', $context['mode']);
        self::assertTrue($context['ambiguous']);
        self::assertSame([1, 2], $context['institute_ids']);
        self::assertFalse(LocationPolicy::canWriteBook($book));
    }

    public function test_separate_course_of_second_location_is_not_disabled_with_entire_user(): void
    {
        $book = $this->book();
        DB::table('courses')->insert(['id' => 2]);
        $this->person(2, 2, 1, 2);
        $this->schedule(false);
        $user = User::findOrFail(1);
        self::assertSame('archive', LocationPolicy::forCourse($user, 1)['mode']);
        self::assertSame('normal', LocationPolicy::forCourse($user, 2)['mode']);
        self::assertSame('archive', LocationPolicy::forBook($book)['mode']);
    }

    public function test_measure_disambiguates_known_person_contexts_and_unknown_context_blocks_automation(): void
    {
        $book = $this->book('A');
        DB::table('persons')->where('id', 1)->update(['programdata' => json_encode(['massnahme_id' => 'A'])]);
        $this->person(2, 2, 1, 1, ['massnahme_id' => 'B']);
        $this->schedule(false);
        self::assertSame(1, LocationPolicy::forBook($book)['institute_id']);
        DB::table('course_participant_enrollments')->delete();
        self::assertSame('conflict', LocationPolicy::forBook($book)['mode']);
        self::assertTrue(LocationPolicy::forBook($book)['ambiguous']);
    }

    public function test_historical_inactive_enrollment_keeps_book_location_but_is_not_a_writable_course(): void
    {
        $book = $this->book();
        DB::table('course_participant_enrollments')->update(['is_active' => 0, 'deleted_at' => '2026-10-18 10:00:00']);
        $this->schedule(false);
        self::assertSame('archive', LocationPolicy::forBook($book)['mode']);
        self::assertSame('conflict', LocationPolicy::forCourse(User::findOrFail(1), 1)['mode']);
    }

    public function test_malformed_measure_context_is_read_only_instead_of_guessing_a_location(): void
    {
        $book = $this->book('A');
        DB::table('persons')->where('id', 1)->update(['programdata' => '"unresolved legacy context"']);
        $context = LocationPolicy::forBook($book);
        self::assertSame('conflict', $context['mode']);
        self::assertTrue($context['ambiguous']);
        self::assertFalse($context['stamp_enabled']);
    }

    public function test_preview_counts_only_submitted_entries_with_waiting_review_tasks(): void
    {
        $book = $this->book();
        foreach ([0, 1, 2, 3] as $status) {
            DB::table('report_book_entries')->insert(['report_book_id' => $book->id, 'status' => $status]);
        }
        DB::table('admin_tasks')->insert([
            ['context_type' => $book->getMorphClass(), 'context_id' => $book->id, 'task_type' => 'reportbook_review', 'status' => 1],
            ['context_type' => $book->getMorphClass(), 'context_id' => $book->id, 'task_type' => 'other', 'status' => 0],
            ['context_type' => $book->getMorphClass(), 'context_id' => $book->id, 'task_type' => 'reportbook_review', 'status' => 2],
        ]);
        $preview = LocationPolicy::preview(1);
        self::assertSame(1, $preview['participants']);
        self::assertSame(1, $preview['eligible_entries']);
        self::assertSame(1, $preview['eligible_tasks']);
    }

    public function test_schema_rollback_cannot_destroy_existing_revision_history(): void
    {
        $this->schedule(false);
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_19_180000_create_report_book_location_policies.php'))->down();
    }

    private function schedule(bool $required, bool $stampEnabled = false, string $date = '2026-10-19', int $version = 0, array $stamp = []): array
    {
        return LocationPolicy::schedule(1, array_merge(['required' => $required, 'stamp_enabled' => $stampEnabled, 'publication_date' => $date], $stamp), 7, $version);
    }

    private function book(?string $measure = null): ReportBook
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Synthetic participant']);
        DB::table('courses')->insert(['id' => 1]);
        $this->person(1, 1, 1, 1);
        $id = DB::table('report_books')->insertGetId(['user_id' => 1, 'course_id' => 1, 'massnahme_id' => $measure]);
        return ReportBook::findOrFail($id);
    }

    private function person(int $id, int $institute, int $user, int $course, array $program = []): void
    {
        DB::table('persons')->insert(['id' => $id, 'user_id' => $user, 'institut_id' => $institute, 'programdata' => json_encode($program)]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course, 'person_id' => $id]);
    }
}
