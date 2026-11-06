<?php

namespace Tests\Feature;

use App\Livewire\Tutor\Courses\ManageCourseResults;
use App\Livewire\User\Program\Course\CourseRatingFormModal;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseRating;
use App\Models\CourseResult;
use App\Models\Person;
use App\Models\User;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\CourseApiServices\CourseRatingsSyncService;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;
use App\Services\Courses\CourseLecturerAccess as Access;
use App\Services\Courses\CourseLecturerEvidence;
use App\Services\Courses\LecturerPlanProjector;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CourseLecturerPlanTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'session.driver' => 'array',
                'activitylog.dispatch_enabled' => false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00', 'Europe/Berlin'));
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->string('person_id')->nullable();
            $t->string('teilnehmer_id')->nullable();
            $t->string('teilnehmer_fnr')->nullable();
            $t->integer('institut_id')->nullable();
            $t->string('vorname')->nullable();
            $t->string('nachname')->nullable();
            $t->json('programdata')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->date('date');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->text('notes')->nullable();
            $t->integer('note_status')->default(0);
            $t->integer('notes_author_user_id')->nullable();
            $t->integer('notes_revision')->nullable();
            $t->string('notes_assignment_id')->nullable();
            $t->json('day_sessions')->nullable();
            $t->json('attendance_data')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->integer('person_id');
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            foreach (['teilnehmer_id', 'tn_baustein_id', 'baustein_id', 'klassen_id', 'termin_id', 'vtz', 'kurzbez_ba', 'status', 'results', 'notes', 'source_snapshot', 'source_last_upd', 'last_synced_at'] as $field) {
                $t->text($field)->nullable();
            }
        });
        Schema::create('course_ratings', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->integer('user_id');
            $t->integer('participant_id')->nullable();
            $t->boolean('is_anonymous')->default(false);
            $t->boolean('skip_course_rating')->default(false);
            $t->text('message')->nullable();
            foreach (['kb_1', 'kb_2', 'kb_3', 'sa_1', 'sa_2', 'sa_3', 'il_1', 'il_2', 'il_3', 'do_1', 'do_2', 'do_3'] as $field) {
                $t->integer($field)->nullable();
            }$t->timestamps();
        });
        Schema::create('course_results', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->integer('person_id');
            $t->integer('result')->nullable();
            $t->string('status')->nullable();
            $t->integer('updated_by')->nullable();
            $t->integer('remote_uid')->nullable();
            $t->string('sync_state')->nullable();
            $t->date('remote_upd_date')->nullable();
            $t->timestamps();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->morphs('fileable');
            $t->integer('user_id')->nullable();
            $t->string('type')->nullable();
            $t->string('path')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        DB::table('users')->insert([['id' => 1, 'name' => 'Alt'], ['id' => 2, 'name' => 'Neu'], ['id' => 3, 'name' => 'Teilnehmer'], ['id' => 4, 'name' => 'Fremd']]);
        DB::table('persons')->insert([
            ['id' => 11, 'user_id' => 1, 'person_id' => 'UVS-A', 'teilnehmer_id' => null, 'vorname' => 'Alte', 'nachname' => 'Lehrkraft'],
            ['id' => 22, 'user_id' => 2, 'person_id' => 'UVS-B', 'teilnehmer_id' => null, 'vorname' => 'Neue', 'nachname' => 'Lehrkraft'],
            ['id' => 33, 'user_id' => 3, 'person_id' => 'UVS-T', 'teilnehmer_id' => 'TN-33', 'vorname' => 'Test', 'nachname' => 'Teilnehmer'],
            ['id' => 44, 'user_id' => 4, 'person_id' => 'UVS-X', 'teilnehmer_id' => null, 'vorname' => 'Fremde', 'nachname' => 'Lehrkraft'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function course(bool $project = true): Course
    {
        $course = Course::withoutEvents(fn () => Course::create(['title' => 'DLO1', 'klassen_id' => 'K-'.uniqid(), 'termin_id' => 'T-1',
            'planned_start_date' => '2026-10-19', 'planned_end_date' => '2026-11-08', 'primary_tutor_person_id' => 11]));
        DB::table('course_days')->insert([['course_id' => $course->id, 'date' => '2026-11-04', 'end_time' => '16:00:00'], ['course_id' => $course->id, 'date' => '2026-11-06', 'end_time' => '16:00:00']]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course->id, 'person_id' => 33, 'is_active' => true]);
        if ($project) {
            LecturerPlanProjector::apply($course, $this->source());
        }

        return $course;
    }

    private function source(int $revision = 1): array
    {
        return ['version' => 1, 'status' => 'ready', 'revision' => $revision, 'completion_at' => '2026-11-06T16:00:00+01:00', 'closing_assignment_id' => 'B',
            'assignments' => [
                ['assignment_id' => 'A', 'person_id' => 'UVS-A', 'mitarbeiter_id' => 'MA-A', 'contract_id' => '101', 'legacy_assignment_uid' => '101', 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-04'],
                ['assignment_id' => 'B', 'person_id' => 'UVS-B', 'mitarbeiter_id' => 'MA-B', 'contract_id' => '202', 'legacy_assignment_uid' => '202', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-08'],
            ]];
    }

    public function test_midnight_switch_uses_assignment_dates_and_keeps_former_course_readable(): void
    {
        $course = $this->course();
        $old = User::find(1);
        $new = User::find(2);
        $day = $course->days()->whereDate('date', '2026-11-04')->first();
        $this->assertTrue(Access::canWriteDay($old, $course, $day));
        $this->assertFalse(Access::canManageCourse($new, $course));
        $this->assertSame(11, $course->tutor->id);
        $this->travelTo(Carbon::parse('2026-11-05 00:00', 'Europe/Berlin'));
        $this->assertTrue(Access::canView($old, $course));
        $this->assertFalse(Access::canWriteDay($old, $course, $day));
        $this->assertTrue(Access::canManageCourse($new, $course));
        $this->assertFalse(Access::canWriteDay($new, $course, $day));
        $this->assertSame(22, $course->tutor->id);
        $this->assertTrue(Access::canInvoice($old, $course));
        $this->assertTrue(Access::canInvoice($new, $course));
        $this->assertFalse(Access::canView(User::find(4), $course));
    }

    public function test_all_linked_courses_are_visible_for_current_former_and_future_lecturers(): void
    {
        $first = $this->course();
        $second = $this->course();
        foreach ([11, 22] as $id) {
            $this->assertSame([$first->id, $second->id], Person::find($id)->taughtCourses()->orderBy('id')->pluck('id')->all());
        }
        $this->assertSame(0, Person::find(44)->taughtCourses()->count());
        $this->travelTo(Carbon::parse('2026-11-05 10:00', 'Europe/Berlin'));
        $display = Access::display($first);
        $this->assertTrue($display['multiple']);
        $this->assertSame('Neue Lehrkraft', $display['current']['name']);
        $this->assertSame('Alte Lehrkraft', $display['previous'][0]['name']);
    }

    public function test_same_person_with_two_existing_agreements_has_no_extra_lecturer_ui(): void
    {
        $course = $this->course(false);
        $source = $this->source();
        $source['assignments'][1]['person_id'] = 'UVS-A';
        LecturerPlanProjector::apply($course, $source);
        $this->assertFalse(Access::display($course)['multiple']);
        $html = view('components.courses.lecturer-history', ['course' => $course])->render();
        $this->assertStringNotContainsString('Hauptdozent:', $html);
        $this->assertStringNotContainsString('früherer Hauptdozent', $html);
    }

    public function test_projection_requires_one_unambiguous_existing_course_agreement_per_assignment(): void
    {
        foreach (['missing', 'different', 'separate-contract-reference'] as $invalid) {
            $course = $this->course();
            $source = $this->source(2);
            if ($invalid === 'missing') {
                unset($source['assignments'][1]['legacy_assignment_uid']);
            }
            if ($invalid === 'different') {
                $source['assignments'][1]['legacy_assignment_uid'] = '999';
            }
            if ($invalid === 'separate-contract-reference') {
                $source['assignments'][1]['contract_id'] = 'LV-NEW';
            }
            LecturerPlanProjector::apply($course, $source);
            $this->assertFalse(Access::healthy($course));
            $this->assertSame('202', Access::assignments($course)[1]['contract_id']);
            $this->assertSame('202', Access::assignments($course)[1]['legacy_assignment_uid']);
        }
    }

    public function test_returning_teacher_can_reuse_the_same_existing_agreement(): void
    {
        $course = $this->course(false);
        $source = $this->source();
        $source['assignments'][1]['ends_on'] = '2026-11-06';
        $source['assignments'][] = [...$source['assignments'][0], 'assignment_id' => 'C', 'starts_on' => '2026-11-07', 'ends_on' => '2026-11-08'];
        LecturerPlanProjector::apply($course, $source);
        $this->assertTrue(Access::healthy($course));
        $this->assertSame(['101', '202', '101'], array_column(Access::assignments($course), 'contract_id'));
        $this->assertSame(['101', '202', '101'], array_column(Access::assignments($course), 'legacy_assignment_uid'));
    }

    public function test_projection_ignores_older_and_legacy_payloads_and_rejects_same_revision_changes(): void
    {
        $course = $this->course();
        $two = $this->source(2);
        LecturerPlanProjector::apply($course, $two);
        LecturerPlanProjector::apply($course, $this->source(1));
        LecturerPlanProjector::apply($course, ['status' => 'legacy']);
        $this->assertSame(2, (int) $course->fresh()->lecturer_plan_revision);
        LecturerPlanProjector::apply($course, $two);
        $this->assertTrue(Access::healthy($course));
        $two['assignments'][1]['contract_id'] = '999';
        $two['assignments'][1]['legacy_assignment_uid'] = '999';
        LecturerPlanProjector::apply($course, $two);
        $this->assertFalse(Access::healthy($course));
        $this->assertSame('202', Access::assignments($course)[1]['contract_id']);
        $this->assertFalse(Access::canManageCourse(User::find(1), $course));
        $this->assertFalse(Access::canInvoice(User::find(1), $course));
        LecturerPlanProjector::apply($course, $two);
        $this->assertFalse(Access::healthy($course));
    }

    public function test_invalid_plan_fails_closed_and_first_conflict_remains_visible(): void
    {
        $course = $this->course(false);
        $bad = $this->source();
        $bad['assignments'][1]['starts_on'] = '2026-11-06';
        LecturerPlanProjector::apply($course, $bad);
        $this->assertFalse(Access::healthy($course));
        $this->assertTrue(Access::canView(User::find(1), $course));
        $this->assertSame(1, Person::find(11)->taughtCourses()->count());
        $this->assertFalse(Access::canManageCourse(User::find(1), $course));
        LecturerPlanProjector::apply($course, $this->source(2));
        $this->assertTrue(Access::healthy($course));
        $bad = $this->source(3);
        $bad['revision'] = null;
        LecturerPlanProjector::apply($course, $bad);
        $this->assertFalse(Access::healthy($course));
    }

    public function test_stale_course_instances_cannot_authorize_writes_after_a_new_projection(): void
    {
        $course = $this->course();
        $stale = Course::find($course->id);
        $source = $this->source(2);
        $source['assignments'][0]['ends_on'] = '2026-11-03';
        $source['assignments'][1]['starts_on'] = '2026-11-04';
        LecturerPlanProjector::apply($course, $source);
        $this->assertFalse(Access::canManageCourse(User::find(1), $stale));
        $this->assertTrue(Access::canManageCourse(User::find(2), $stale));
    }

    public function test_same_revision_cannot_change_completion_or_closing_target_and_unknown_people_fail_closed(): void
    {
        $course = $this->course();
        $change = $this->source();
        $change['completion_at'] = '2026-11-06T17:00:00+01:00';
        LecturerPlanProjector::apply($course, $change);
        $this->assertFalse(Access::healthy($course));
        LecturerPlanProjector::apply($course, $this->source(2));
        $this->assertTrue(Access::healthy($course));
        $change = $this->source(3);
        $change['assignments'][1]['person_id'] = 'NOT-IMPORTED';
        LecturerPlanProjector::apply($course, $change);
        $this->assertFalse(Access::healthy($course));
        $this->assertSame(22, Access::assignments($course)[1]['person_id']);
        LecturerPlanProjector::apply($course, $this->source(4));
        $this->assertTrue(Access::healthy($course));
        $change = $this->source(5);
        $change['closing_assignment_id'] = 'A';
        LecturerPlanProjector::apply($course, $change);
        $this->assertFalse(Access::healthy($course));
    }

    public function test_future_main_teacher_is_displayed_but_cannot_write_before_assignment_start(): void
    {
        $course = $this->course();
        $this->travelTo(Carbon::parse('2026-10-18 20:00', 'Europe/Berlin'));
        $this->assertSame(11, Access::currentPersonId($course));
        $this->assertTrue(Access::canView(User::find(1), $course));
        $this->assertFalse(Access::canManageCourse(User::find(1), $course));
        $this->assertFalse(Access::canInvoice(User::find(1), $course));
    }

    public function test_old_legacy_instances_and_cached_file_relations_cannot_bypass_new_plan_guards(): void
    {
        $course = $this->course(false);
        $stale = Course::find($course->id);
        $file = \App\Models\File::withoutEvents(fn () => $course->files()->create(['type' => 'invoice', 'user_id' => 2, 'path' => 'synthetic.pdf']));
        $file->setRelation('fileable', $stale);
        $file->type = 'roter_faden';
        $source = $this->source();
        $source['assignments'][0]['ends_on'] = '2026-11-03';
        $source['assignments'][1]['starts_on'] = '2026-11-04';
        LecturerPlanProjector::apply($course, $source);
        $this->actingAs(User::find(1));
        foreach ([fn () => CourseLecturerEvidence::guardMaterials($stale), fn () => CourseLecturerEvidence::guardResults($stale), fn () => CourseLecturerEvidence::guardFileRead($file)] as $work) {
            try {
                $work();
                $this->fail('Stale guard bypassed new plan');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        try {
            CourseLecturerEvidence::guardFileDelete($file);
            $this->fail('Invoice evidence deletion accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->actingAs(User::find(4));
        try {
            CourseLecturerEvidence::guardView($stale);
            $this->fail('Foreign read accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_day_save_rejects_concurrent_document_overwrite_and_releases_transaction(): void
    {
        $course = $this->course();
        $this->actingAs(User::find(1));
        $day = $course->days()->whereDate('date', '2026-11-04')->first();
        $stale = CourseDay::find($day->id);
        $day->update(['notes' => 'First confirmed draft']);
        try {
            $stale->update(['notes' => 'Stale overwrite']);
            $this->fail('Concurrent overwrite accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('First confirmed draft', $day->fresh()->notes);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_day_model_write_without_actor_is_denied_but_explicit_internal_import_is_allowed(): void
    {
        $course = $this->course();
        $day = $course->days()->whereDate('date', '2026-11-04')->first();
        try {
            $day->update(['notes' => 'No actor']);
            $this->fail('Unauthenticated write accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $day->refresh();
        Access::runAsSystem(fn () => $day->update(['notes' => 'Trusted UVS import']));
        $this->assertSame('Trusted UVS import', $day->fresh()->notes);
        $this->assertNull($day->fresh()->notes_author_user_id);
        $this->assertFalse(Access::isSystemSync());
    }

    public function test_completion_is_after_last_lesson_and_pins_the_api_closing_assignment(): void
    {
        $course = $this->course();
        $this->travelTo(Carbon::parse('2026-11-06 15:59:59', 'Europe/Berlin'));
        $this->assertFalse(Access::canManageResults(User::find(2), $course));
        $this->travelTo(Carbon::parse('2026-11-06 16:00', 'Europe/Berlin'));
        $this->assertTrue(Access::canManageResults(User::find(2), $course));
        $this->assertFalse(Access::canManageResults(User::find(1), $course));
        $this->assertSame('B', Access::pinClosingAssignment($course)['assignment_id']);
        $this->assertSame('B', $course->fresh()->lecturer_closing_assignment_id);
        $change = $this->source(2);
        $change['assignments'][1]['contract_id'] = '999';
        $change['assignments'][1]['legacy_assignment_uid'] = '999';
        LecturerPlanProjector::apply($course, $change);
        $this->assertFalse(Access::healthy($course));
        $this->assertSame('202', Access::assignments($course)[1]['contract_id']);
        LecturerPlanProjector::apply($course, $this->source(3));
        $this->assertTrue(Access::healthy($course));
    }

    public function test_friday_closing_teacher_can_differ_from_last_calendar_assignment(): void
    {
        $course = $this->course(false);
        $source = $this->source();
        $source['assignments'][0]['ends_on'] = '2026-11-06';
        $source['assignments'][1]['starts_on'] = '2026-11-07';
        $source['closing_assignment_id'] = 'A';
        LecturerPlanProjector::apply($course, $source);
        $this->travelTo(Carbon::parse('2026-11-08 17:00', 'Europe/Berlin'));
        $this->assertSame(22, Access::currentPersonId($course));
        $this->assertTrue(Access::canManageResults(User::find(1), $course));
        $this->assertFalse(Access::canManageResults(User::find(2), $course));
    }

    public function test_legacy_and_coaching_keep_primary_tutor_and_system_scope_always_resets(): void
    {
        $course = $this->course(false);
        $this->assertFalse(Access::hasPlan($course));
        $this->assertTrue(Access::canManageCourse(User::find(1), $course));
        $course->forceFill(['type' => 'coaching', 'lecturer_plan' => ['status' => 'conflict']])->saveQuietly();
        $this->assertFalse(Access::hasPlan($course));
        $this->assertSame(11, $course->tutor->id);
        try {
            Access::runAsSystem(function () {
                $this->assertTrue(Access::isSystemSync());
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertFalse(Access::isSystemSync());
    }

    public function test_participant_rating_uses_frozen_server_target_and_is_idempotent(): void
    {
        $course = $this->course();
        $this->actingAs(User::find(3));
        $this->travelTo(Carbon::parse('2026-11-06 17:00', 'Europe/Berlin'));
        $modal = app(CourseRatingFormModal::class);
        $modal->open(['course_id' => $course->klassen_id, 'tutor_id' => 11, 'dozent' => 'Falsch']);
        $this->assertSame('Neue Lehrkraft', $modal->dozent);
        $modal->skipCourseRating();
        $modal->alreadyRated = false;
        $modal->skipCourseRating();
        $rating = CourseRating::sole();
        $this->assertSame('B', $rating->lecturer_assignment_id);
        $this->assertSame(22, (int) $rating->lecturer_person_id);
        $this->assertSame('202', $rating->lecturer_contract_id);
        $this->assertSame('MA-B', $rating->lecturer_staff_id);
        $this->assertSame('B', $course->fresh()->lecturer_closing_assignment_id);
    }

    public function test_nonparticipant_and_early_rating_are_rejected(): void
    {
        $course = $this->course();
        $this->actingAs(User::find(3));
        try {
            app(CourseRatingFormModal::class)->open(['course_id' => $course->klassen_id]);
            $this->fail('Early rating accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->travelTo(Carbon::parse('2026-11-06 17:00', 'Europe/Berlin'));
        $this->actingAs(User::find(4));
        try {
            app(CourseRatingFormModal::class)->open(['course_id' => $course->klassen_id]);
            $this->fail('Foreign rating accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(0, CourseRating::count());
    }

    public function test_readonly_result_mount_never_loads_remote_and_every_write_action_is_guarded(): void
    {
        $course = $this->course();
        $this->actingAs(User::find(1));
        $service = \Mockery::mock(CourseResultsSyncService::class);
        $service->shouldReceive('loadFromRemote')->never();
        $service->shouldReceive('normalizeLocalStatus')->andReturn(null);
        $service->shouldReceive('normalizeLocalResult')->andReturn(null);
        app()->instance(CourseResultsSyncService::class, $service);
        $component = app(ManageCourseResults::class);
        $component->mount($course);
        $this->travelTo(Carbon::parse('2026-11-05 10:00', 'Europe/Berlin'));
        \Livewire\Livewire::test(ManageCourseResults::class, ['course' => $course])
            ->assertSee('Ergebnisse sind lesbar.')->assertSee('Test')->assertSee('disabled', false);
        foreach (['saveOne' => ['33'], 'setStatus' => ['33', 'V'], 'clearResult' => ['33'], 'syncResults' => [], 'reloadFromUvs' => [], 'deleteLocalResults' => [], 'deleteRemoteAndLocalResults' => [], 'updatedIsExternalExam' => [true]] as $method => $args) {
            try {
                $component->$method(...$args);
                $this->fail($method.' was allowed before completion');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, CourseResult::count());
    }

    public function test_result_push_rejects_unbound_or_stale_actor_claim_before_api(): void
    {
        $course = $this->course();
        $this->travelTo(Carbon::parse('2026-11-06 17:00', 'Europe/Berlin'));
        DB::table('course_results')->insert(['course_id' => $course->id, 'person_id' => 33, 'result' => 50, 'status' => '+', 'updated_by' => 1, 'sync_state' => 'dirty', 'lecturer_assignment_id' => 'A', 'lecturer_plan_revision' => 1]);
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->never();
        try {
            (new CourseResultsSyncService($api))->syncToRemote($course);
            $this->fail('Old teacher push accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('dirty', CourseResult::sole()->sync_state);
    }

    public function test_result_push_includes_assignment_revision_and_staff(): void
    {
        $course = $this->course();
        $this->travelTo(Carbon::parse('2026-11-06 17:00', 'Europe/Berlin'));
        DB::table('course_results')->insert(['course_id' => $course->id, 'person_id' => 33, 'result' => 50, 'status' => '+', 'updated_by' => 2, 'sync_state' => 'dirty', 'lecturer_assignment_id' => 'B', 'lecturer_plan_revision' => 1]);
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('POST', '/api/course/courseresults/syncdata', \Mockery::on(function ($payload) {
            return $payload['lecturer_assignment_id'] === 'B' && $payload['lecturer_plan_revision'] === 1 && $payload['mitarbeiter_id'] === 'MA-B' && $payload['changes'][0]['teilnehmer_id'] === 'TN-33';
        }), [])->andReturn(['ok' => true, 'data' => ['pushed' => ['results' => [['teilnehmer_id' => 'TN-33', 'action' => 'updated']]]]]);
        $this->assertTrue((new CourseResultsSyncService($api))->syncToRemote($course));
        $this->assertSame('synced', CourseResult::sole()->sync_state);
    }

    public function test_rating_push_targets_closing_assignment_and_refuses_foreign_rating(): void
    {
        $course = $this->course();
        $this->travelTo(Carbon::parse('2026-11-06 17:00', 'Europe/Berlin'));
        DB::table('course_ratings')->insert(['course_id' => $course->id, 'user_id' => 3, 'participant_id' => 3, 'do_1' => 2,
            'lecturer_assignment_id' => 'B', 'lecturer_person_id' => 22, 'lecturer_contract_id' => '202', 'lecturer_staff_id' => 'MA-B', 'lecturer_plan_revision' => 1]);
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('POST', '/api/course/ratings/syncdata', \Mockery::on(fn ($p) => $p['lecturer_assignment_id'] === 'B' && $p['lecturer_plan_revision'] === 1 && $p['mitarbeiter_id'] === 'MA-B'), [])->andReturn(['ok' => true]);
        $service = new CourseRatingsSyncService($api);
        $this->assertTrue($service->syncToRemote($course));
        DB::table('course_ratings')->update(['lecturer_contract_id' => 'FOREIGN']);
        try {
            $service->syncToRemote($course);
            $this->fail('Foreign rating was sent');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
