<?php

namespace Tests\Feature;

use App\Models\{CoachingContract, CoachingNotice, Course, Person, User};
use App\Services\Coaching\{CourseProjector, NoticeService, PlanService, SyncService};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{Notification, Queue, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CoachingLifecycleAuditTest extends TestCase
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
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake(); Notification::fake();
        \Carbon\Carbon::setTestNow('2026-09-23 08:00:00');
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->default('guest'); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('person_id'); $t->integer('institut_id');
            $t->string('role')->default('guest'); $t->json('statusdata')->nullable(); $t->json('programdata')->nullable();
            $t->timestamp('last_api_update')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->text('message'); $t->integer('from_user'); $t->integer('to_user'); $t->integer('status'); $t->timestamps(); });
        Schema::create('files', function (Blueprint $t) { $t->id(); $t->morphs('fileable'); $t->unsignedBigInteger('user_id'); $t->string('type'); $t->string('path')->nullable(); $t->timestamps(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        \App\Models\Setting::setValue('coaching', 'enabled', true);
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://schulnetz.example.test');
        foreach (['2025_09_10_152938_create_courses_table.php', '2025_09_10_152939_create_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php', '2026_09_17_080000_create_coaching_planning_tables.php',
            '2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php', '2026_09_22_100000_create_coaching_notices.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('course_days', function (Blueprint $t) { $t->integer('note_status')->default(0); $t->json('settings')->nullable(); $t->timestamp('attendance_updated_at')->nullable(); });
        Person::withoutEvents(function () {
            User::forceCreate(['id' => 1, 'name' => 'System', 'role' => 'admin']);
            foreach ([['participant', '1-100', 'guest'], ['tutor', '1-200', 'tutor']] as [$label, $id, $role]) {
                $user = User::create(['name' => $label, 'email' => $label.'@example.test', 'role' => $role]);
                Person::create(['user_id' => $user->id, 'person_id' => $id, 'institut_id' => 1, 'role' => $role]);
            }
        });
    }

    protected function tearDown(): void { \Carbon\Carbon::setTestNow(); parent::tearDown(); }

    private function row(array $changes = []): array
    {
        return array_replace(['id' => 1, 'institut_id' => 1, 'person_id' => '1-100', 'beratung_id' => 'test-1',
            'planning_fingerprint' => str_repeat('a', 64), 'tutor_person_id' => '1-200', 'title' => 'Audit Einzelcoaching',
            'agreed_minutes' => 90, 'unit_minutes' => 45, 'version' => str_repeat('a', 64), 'status' => 'draft'], $changes);
    }

    private function items(): array
    {
        return [['id' => (string)Str::uuid(), 'date' => '2026-10-01', 'start' => '09:00', 'end' => '10:30',
            'topic' => 'Bewerbung', 'format' => 'online', 'location' => 'Online']];
    }

    private function confirmed(array $changes = []): CoachingContract
    {
        app(SyncService::class)->importContract($this->row($changes));
        $contract = CoachingContract::where('uvs_contract_id', $changes['id'] ?? 1)->firstOrFail();
        $plans = app(PlanService::class);
        $plans->propose($contract->id, User::where('role', 'tutor')->firstOrFail(), 0, $this->items());
        $plans->confirm($contract->id, User::where('role', 'tutor')->firstOrFail(), 1);
        $plans->confirm($contract->id, User::where('role', 'guest')->firstOrFail(), 1);
        return $contract->fresh();
    }

    public function test_confirmed_draft_reserves_the_tutor_before_uvs_contract_activation(): void
    {
        $this->confirmed();
        app(SyncService::class)->importContract($this->row(['id' => 2, 'beratung_id' => 'test-2']));
        $second = CoachingContract::where('uvs_contract_id', 2)->firstOrFail();
        $tutor = User::where('role', 'tutor')->firstOrFail();
        app(PlanService::class)->propose($second->id, $tutor, 0, $this->items());
        $this->expectException(ValidationException::class);
        app(PlanService::class)->confirm($second->id, $tutor, 1);
    }

    public function test_projector_never_releases_a_draft_directly(): void
    {
        $contract = $this->confirmed();
        $this->expectException(\LogicException::class);
        app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
    }

    public function test_changed_confirmed_scope_deactivates_existing_course_and_suppresses_old_release(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $plan = $contract->confirmedPlan;
        app(CourseProjector::class)->project($contract, $plan);
        app(NoticeService::class)->record($contract, 'released', '1');
        app(SyncService::class)->importContract($this->row(['status' => 'active', 'version' => str_repeat('b',64),
            'planning_fingerprint' => str_repeat('b',64), 'agreed_minutes' => 180]));
        $this->assertFalse((bool)Course::firstOrFail()->is_active);
        app(NoticeService::class)->deliverPending();
        $this->assertSame(0, CoachingNotice::where('kind', 'released')->whereNotNull('mail_sent_at')->count());
        $this->assertSame(2, CoachingNotice::where('kind', 'released')->whereNotNull('dismissed_at')->count());
    }

    public function test_delayed_stop_notification_is_not_sent_after_resumption(): void
    {
        $sync = app(SyncService::class);
        $sync->importContract($this->row());
        $sync->importContract($this->row(['status' => 'inactive', 'version' => str_repeat('b',64)]));
        $sync->importContract($this->row());
        app(NoticeService::class)->deliverPending();
        $this->assertSame(0, CoachingNotice::where('kind', 'stopped')->whereNotNull('mail_sent_at')->count());
        $this->assertSame(2, CoachingNotice::where('kind', 'stopped')->whereNotNull('dismissed_at')->count());
    }

    public function test_repeated_deactivation_and_resume_announces_each_actual_transition_once(): void
    {
        $sync = app(SyncService::class);
        $sync->importContract($this->row());
        for ($cycle = 0; $cycle < 2; $cycle++) {
            $sync->importContract($this->row(['status' => 'inactive', 'version' => str_repeat('b',64)]));
            $sync->importContract($this->row(['status' => 'inactive', 'version' => str_repeat('b',64)]));
            app(NoticeService::class)->deliverPending();
            $sync->importContract($this->row());
            $sync->importContract($this->row());
            app(NoticeService::class)->deliverPending();
        }
        $this->assertSame(4, CoachingNotice::where('kind', 'stopped')->whereNotNull('mail_sent_at')->count());
        $this->assertSame(4, CoachingNotice::where('kind', 'resumed')->whereNotNull('mail_sent_at')->count());
    }

    public function test_coaching_session_panel_opens_and_saves_normal_documentation_without_changing_schedule(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $day = $course->days()->firstOrFail();
        $original = $day->day_sessions;
        $panel = new \App\Livewire\Tutor\Courses\CourseDaysPanel();
        $panel->mount($course->id);
        $this->assertSame($original[0]['id'], $panel->selectedDaySessionId);
        $this->assertSame('Bewerbung', $panel->selectedDaySessionTopic);
        $panel->selectedDaySessionNotes = 'Unterlagen gemeinsam bearbeitet.';
        $panel->updatedSelectedDaySessionNotes();
        $panel->selectedDaySessionTopic = 'Bewerbungsunterlagen';
        $panel->updatedSelectedDaySessionTopic();
        $day->refresh();
        $this->assertSame('Unterlagen gemeinsam bearbeitet.', $day->getSessionNotes($original[0]['id']));
        $this->assertSame('Bewerbungsunterlagen', $day->getSessionTopic($original[0]['id']));
        $this->assertCount(1, $day->day_sessions);
        $this->assertSame('09:00', $day->day_sessions[0]['start']);
        $changed = $day->day_sessions; $changed[0]['start'] = '08:30';
        $this->expectException(ValidationException::class);
        $day->update(['day_sessions' => $changed]);
    }

    public function test_disabling_coaching_also_blocks_documentation_through_standard_course_actions(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        \App\Models\Setting::setValue('coaching', 'enabled', false);
        $this->expectException(ValidationException::class);
        $course->days()->firstOrFail()->update(['notes' => 'Not permitted while disabled']);
    }

    public function test_attendance_uses_agreed_end_time_instead_of_treating_ue_as_clock_hours(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $panel = new class extends \App\Livewire\Tutor\Courses\ParticipantsTable {
            public function plannedTimes(): array { return $this->plannedTimesForSelectedDay(); }
        };
        $panel->selectedDay = $course->days()->firstOrFail();
        [$start, $end] = $panel->plannedTimes();
        $this->assertSame('09:00', $start->format('H:i'));
        $this->assertSame('10:30', $end->format('H:i'));
    }

    public function test_suspended_plan_is_cancelled_in_calendar_export(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $contract->update(['contract_version' => str_repeat('b',64)]);
        $this->actingAs(User::where('role', 'guest')->firstOrFail());
        $response = app(\App\Http\Controllers\CoachingCalendarController::class)($contract->id);
        $this->assertStringContainsString('STATUS:CANCELLED', $response->getContent());
        $this->assertStringNotContainsString('STATUS:CONFIRMED', $response->getContent());
    }

    public function test_remote_stop_is_imported_even_when_confirmed_tutor_was_deleted_locally(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        Person::withoutEvents(fn () => $contract->tutor->delete());
        app(SyncService::class)->importContract($this->row(['status' => 'inactive', 'version' => str_repeat('b',64)]));
        $this->assertSame('inactive', $contract->fresh()->contract_status);
        $this->assertFalse((bool)$contract->fresh()->course->is_active);
        $this->assertFalse($contract->fresh()->startReady());
    }

    public function test_missing_confirmed_identity_suspends_active_course_even_without_uvs_version_change(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        Person::withoutEvents(fn () => $contract->tutor->delete());
        app(SyncService::class)->importContract($this->row(['status' => 'active']));
        $this->assertFalse((bool)$contract->fresh()->course->is_active);
        $this->assertFalse($contract->fresh()->startReady());
        $this->assertSame(2, CoachingNotice::where('kind', 'contract_review')->count());
        $this->actingAs($contract->participant->user);
        $calendar = app(\App\Http\Controllers\CoachingCalendarController::class)($contract->id)->getContent();
        $this->assertStringContainsString('STATUS:CANCELLED', $calendar);
        $this->assertStringNotContainsString('STATUS:CONFIRMED', $calendar);
        $this->expectException(ValidationException::class);
        $contract->fresh()->course->days()->firstOrFail()->update(['notes' => 'Blocked after identity removal']);
    }

    public function test_reassigning_confirmed_tutor_imports_review_without_preserving_old_release(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $replacement = Person::withoutEvents(function () {
            $user = User::create(['name' => 'New tutor', 'email' => 'newtutor@example.test', 'role' => 'tutor']);
            return Person::create(['user_id' => $user->id, 'person_id' => '1-201', 'institut_id' => 1, 'role' => 'tutor']);
        });
        app(SyncService::class)->importContract($this->row(['status' => 'active', 'tutor_person_id' => '1-201',
            'version' => str_repeat('b',64), 'planning_fingerprint' => str_repeat('b',64)]));
        $this->assertSame($replacement->id, $contract->fresh()->tutor_person_id);
        $this->assertFalse((bool)$contract->fresh()->course->is_active);
        $this->assertNull($contract->fresh()->course->primary_tutor_person_id);
        $this->assertSame(2, CoachingNotice::where('kind', 'contract_review')->count());
        $this->assertSame(1, CoachingNotice::where('kind', 'assignment_removed')->count());
    }

    public function test_disabling_coaching_also_blocks_topic_only_edits(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        \App\Models\Setting::setValue('coaching', 'enabled', false);
        $this->expectException(ValidationException::class);
        $course->days()->firstOrFail()->update(['topic' => 'Not permitted while disabled']);
    }

    public function test_foreign_tutor_cannot_read_coaching_documentation_through_child_panel_day_action(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $intruder = User::create(['name' => 'Other tutor', 'role' => 'tutor']);
        $this->actingAs($intruder);
        $panel = new \App\Livewire\Tutor\Courses\CourseDocumentationPanel();
        $panel->courseId = $course->id;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $panel->selectDay($course->days()->firstOrFail()->id);
    }

    public function test_foreign_tutor_cannot_write_coaching_attendance_after_manipulating_child_panel_ids(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $intruder = User::create(['name' => 'Other tutor', 'role' => 'tutor']);
        $this->actingAs($intruder);
        $panel = new \App\Livewire\Tutor\Courses\ParticipantsTable();
        $panel->courseId = $course->id;
        $panel->selectedDayId = $course->days()->firstOrFail()->id;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $panel->markPresent($contract->participant_person_id);
    }

    public function test_attendance_rejects_a_person_not_enrolled_in_the_coaching(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs($contract->tutor->user);
        $panel = new \App\Livewire\Tutor\Courses\ParticipantsTable();
        $panel->courseId = $course->id;
        $panel->selectedDayId = $course->days()->firstOrFail()->id;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $panel->markPresent(99999);
    }

    public function test_signature_event_without_a_real_signature_does_not_complete_coaching_documentation(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs($contract->tutor->user);
        $panel = new \App\Livewire\Tutor\Courses\CourseDocumentationPanel();
        $panel->mount($course->id);
        $panel->dayNotes = 'Dokumentierter Inhalt';
        $panel->saveNotes();
        $panel->handleSignatureCompleted();
        $this->assertSame(\App\Models\CourseDay::NOTE_STATUS_DRAFT, $panel->selectedDay->fresh()->note_status);
    }

    public function test_coaching_signature_cannot_be_attached_by_a_foreign_tutor(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs(User::create(['name' => 'Foreign tutor', 'role' => 'tutor']));
        $form = new class extends \App\Livewire\Tools\Signatures\SignatureForm {
            public function target() { return $this->resolveFileable(); }
        };
        $form->fileableType = \App\Models\CourseDay::class;
        $form->fileableId = $course->days()->firstOrFail()->id;
        $form->fileType = 'sign_courseday_doku_tutor';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $form->target();
    }

    public function test_coaching_documentation_completes_only_with_its_own_tutor_signature(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs($contract->tutor->user);
        $panel = new \App\Livewire\Tutor\Courses\CourseDocumentationPanel();
        $panel->mount($course->id);
        $panel->dayNotes = 'Dokumentierter Inhalt';
        $panel->saveNotes();
        $signature = $panel->selectedDay->files()->create(['user_id' => auth()->id(), 'type' => 'sign_courseday_doku_tutor']);
        $panel->handleSignatureCompleted(['fileId' => $signature->id, 'fileableId' => $panel->selectedDayId, 'fileableType' => \App\Models\CourseDay::class]);
        $this->assertSame(\App\Models\CourseDay::NOTE_STATUS_COMPLETED, $panel->selectedDay->fresh()->note_status);
        $panel->dayNotes = '';
        $panel->saveNotes();
        $this->assertSame(0, $panel->selectedDay->tutorSignatures()->count());
    }

    public function test_rejected_documentation_edit_does_not_delete_the_previous_signature(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs($contract->tutor->user);
        $panel = new \App\Livewire\Tutor\Courses\CourseDocumentationPanel();
        $panel->mount($course->id);
        $panel->dayNotes = 'Dokumentierter Inhalt';
        $panel->saveNotes();
        $signature = $panel->selectedDay->files()->create(['user_id' => auth()->id(), 'type' => 'sign_courseday_doku_tutor']);
        $contract->update(['contract_status' => 'inactive']);
        $panel->dayNotes = 'Nicht erlaubte Änderung';
        try {
            $panel->saveNotes();
            $this->fail('Stopped coaching must reject documentation changes.');
        } catch (ValidationException $e) {
            $this->assertTrue($panel->selectedDay->tutorSignatures()->whereKey($signature->id)->exists());
            $this->assertSame('Dokumentierter Inhalt', $panel->selectedDay->fresh()->notes);
        }
    }

    public function test_finalize_saves_coaching_notes_before_starting_the_signature_flow(): void
    {
        $contract = $this->confirmed(['status' => 'active']);
        $course = app(CourseProjector::class)->project($contract, $contract->confirmedPlan);
        $this->actingAs($contract->tutor->user);
        $panel = new \App\Livewire\Tutor\Courses\CourseDocumentationPanel();
        $panel->mount($course->id);
        $panel->dayNotes = 'Die vollständige Dokumentation';
        $panel->finalizeDay();
        $this->assertSame('Die vollständige Dokumentation', $panel->selectedDay->fresh()->notes);
        $this->assertSame(\App\Models\CourseDay::NOTE_STATUS_DRAFT, $panel->selectedDay->fresh()->note_status);
    }
}
