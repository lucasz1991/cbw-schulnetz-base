<?php

namespace Tests\Feature;

use App\Models\{CoachingContract, CoachingPlan, Course, Person, Setting, User};
use App\Support\{CoachingParticipantDashboard, CoachingTutorDashboard};
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{Http, Notification, Queue, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class CoachingParticipantDashboardTest extends TestCase
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
        Queue::fake(); Notification::fake(); Http::preventStrayRequests(); Http::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:15', 'Europe/Berlin'));
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('role'); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('person_id'); $t->integer('institut_id'); $t->string('role');
            $t->string('vorname')->nullable(); $t->string('nachname')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        foreach (['2025_09_10_152938_create_courses_table.php', '2026_09_17_080000_create_coaching_planning_tables.php',
            '2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php', '2026_09_22_100000_create_coaching_notices.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Setting::setValue('coaching', 'enabled', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?User $user = null, string $role = 'guest'): Person
    {
        $user ??= User::create(['name' => $role === 'tutor' ? 'Dozent Beispiel' : 'Teilnehmer Beispiel', 'role' => $role]);
        return Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => '1-'.Str::uuid(),
            'institut_id' => 1, 'role' => $role]));
    }

    private function contract(Person $participant, Person $tutor, array $attributes = []): CoachingContract
    {
        $id = CoachingContract::count() + 1;
        return CoachingContract::create($attributes + ['uuid' => (string)Str::uuid(), 'uvs_contract_id' => $id,
            'institut_id' => 1, 'uvs_person_id' => $participant->person_id, 'participant_person_id' => $participant->id,
            'tutor_person_id' => $tutor->id, 'beratung_id' => 'dashboard-'.$id, 'title' => 'Coaching '.$id,
            'agreed_minutes' => 180, 'unit_minutes' => 45, 'contract_version' => str_repeat('a', 64)]);
    }

    private function item(string $date, string $start = '09:00', string $end = '10:30', string $format = 'online'): array
    {
        return ['id' => (string)Str::uuid(), 'date' => $date, 'start' => $start, 'end' => $end,
            'topic' => 'Berufliche Orientierung', 'format' => $format, 'location' => $format === 'online' ? 'Online' : 'Raum 12'];
    }

    private function plan(CoachingContract $contract, array $items, bool $released = true): CoachingPlan
    {
        $plan = CoachingPlan::create(['coaching_contract_id' => $contract->id, 'contract_version' => $contract->contract_version,
            'revision' => 1, 'tutor_person_id' => $contract->tutor_person_id, 'participant_person_id' => $contract->participant_person_id,
            'created_by' => $contract->tutor->user_id, 'items' => $items, 'status' => 'confirmed', 'confirmed_at' => now(),
            'participant_confirmed_at' => now(), 'tutor_confirmed_at' => now()]);
        $contract->update(['revision' => 1, 'confirmed_plan_id' => $plan->id]);
        if ($released) {
            $course = Course::create(['title' => $contract->title, 'klassen_id' => 'ec-'.$contract->uuid,
                'type' => 'coaching', 'vtz' => 'E', 'institut_id' => 1, 'is_active' => true]);
            $contract->update(['course_id' => $course->id]);
        }
        return $plan;
    }

    public function test_scope_uses_all_currently_linked_participant_persons_without_tutor_or_outsider_contracts(): void
    {
        $participant = $this->person(); $user = $participant->user; $tutor = $this->person(null, 'tutor');
        $secondPerson = $this->person($user);
        $first = $this->contract($participant, $tutor); $second = $this->contract($secondPerson, $tutor);
        $outsider = $this->person();
        $this->contract($outsider, $tutor);
        $this->contract($outsider, $participant);
        $initial = app(CoachingParticipantDashboard::class)->build($user);
        $this->assertSame([$second->id, $first->id], array_column($initial['contracts'], 'id'));
        $user->load('persons'); // A cached relation must not authorize a now removed identity.
        $participant->update(['user_id' => $outsider->user_id]);

        $dashboard = app(CoachingParticipantDashboard::class)->build($user);

        $this->assertSame([$second->id], array_column($dashboard['contracts'], 'id'));
        $this->assertNotContains($first->id, array_column($dashboard['contracts'], 'id'));
        $this->assertEquals(4, $dashboard['total_units']);
        $this->assertSame(1, $dashboard['planning_count']);
        $this->assertSame(route('coaching.planning', ['contract' => $second->id]), $dashboard['contracts'][0]['action_url']);
    }

    public function test_upcoming_appointments_use_berlin_time_and_are_ordered_and_limited_without_counting_drafts(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        $contract = $this->contract($participant, $tutor);
        $this->plan($contract, [$this->item('2026-09-25'), $this->item('2026-09-23', '08:00', '09:30'),
            $this->item('2026-09-23', '10:00', '11:00', 'presence'), $this->item('2026-09-24'),
            $this->item('2026-09-26')]);
        $draft = $this->contract($participant, $tutor, ['contract_status' => 'draft']);
        $this->plan($draft, [$this->item('2026-09-23', '10:20', '11:50')], false);
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);

        $this->assertSame(4, $dashboard['upcoming_count']);
        $this->assertSame(6, $dashboard['confirmed_count']);
        $this->assertSame(1, $dashboard['ready_count']);
        $this->assertSame(1, $dashboard['waiting_count']);
        $this->assertSame(0, $dashboard['planning_count']);
        $this->assertCount(3, $dashboard['upcoming_sessions']);
        $this->assertSame(['2026-09-23', '2026-09-24', '2026-09-25'], array_column($dashboard['upcoming_sessions'], 'date'));
        $this->assertSame('onsite', $dashboard['next_session']['mode']);
        $this->assertSame('Mittwoch', $dashboard['next_session']['weekday']);
        $this->assertSame('23.09.2026', $dashboard['next_session']['date_label']);
        $this->assertSame('Dozent Beispiel', $dashboard['next_session']['tutor_name']);
        $this->assertSame($contract->id, $dashboard['next_session']['contract_id']);
        $this->assertSame(60, $dashboard['next_session']['minutes']);
        Http::assertNothingSent(); Queue::assertNothingPushed(); Notification::assertNothingSent();
    }

    public function test_ended_and_future_cancelled_contracts_do_not_inflate_totals_or_upcoming_appointments(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        foreach ([['contract_status' => 'cancelled'], ['cancelled_on' => '2026-10-01'], ['valid_until' => '2026-09-22']] as $attributes) {
            $contract = $this->contract($participant, $tutor, $attributes);
            $this->plan($contract, [$this->item('2026-09-28')]);
        }
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame(['ended', 'ended', 'ended'], array_column($dashboard['contracts'], 'status'));
        $this->assertSame(0, $dashboard['total_units']);
        $this->assertSame(0, $dashboard['confirmed_count']);
        $this->assertSame(0, $dashboard['upcoming_count']);
        $this->assertNull($dashboard['next_session']);
        $this->assertSame('Baustein ansehen', $dashboard['contracts'][0]['action_label']);
    }

    public function test_changed_contract_version_revision_or_actor_is_review_only_and_has_no_current_appointments(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        foreach (['version', 'revision', 'actor'] as $change) {
            $contract = $this->contract($participant, $tutor);
            $this->plan($contract, [$this->item('2026-09-28')]);
            $contract->update(match ($change) {
                'version' => ['contract_version' => str_repeat('b', 64)],
                'revision' => ['revision' => 2],
                default => ['tutor_person_id' => $this->person(null, 'tutor')->id],
            });
        }
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame(['review', 'review', 'review'], array_column($dashboard['contracts'], 'status'));
        $this->assertSame(0, $dashboard['confirmed_count']);
        $this->assertSame(0, $dashboard['upcoming_count']);
        $this->assertSame(0, $dashboard['ready_count']);
    }

    public function test_awaiting_tutor_confirmation_is_not_counted_as_participant_action_or_a_fixed_appointment(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        $contract = $this->contract($participant, $tutor, ['contract_status' => 'draft']);
        $plan = $this->plan($contract, [$this->item('2026-09-28')], false);
        $contract->update(['confirmed_plan_id' => null]);
        $plan->update(['status' => 'proposed', 'confirmed_at' => null, 'tutor_confirmed_at' => null]);
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame(1, $dashboard['waiting_count']);
        $this->assertSame(0, $dashboard['planning_count']);
        $this->assertSame(0, $dashboard['confirmed_count']);
        $this->assertSame(0, $dashboard['upcoming_count']);
        $this->assertStringContainsString('Rückmeldung des Dozenten', $dashboard['contracts'][0]['planning_label']);

        $plan->update(['participant_confirmed_at' => null, 'tutor_confirmed_at' => now()]);
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame(1, $dashboard['planning_count']);
        $this->assertSame('Termine abstimmen', $dashboard['contracts'][0]['action_label']);
    }

    public function test_disabled_feature_and_soft_deleted_identity_return_empty_dashboard(): void
    {
        $participant = $this->person(); $user = $participant->user; $tutor = $this->person(null, 'tutor');
        $contract = $this->contract($participant, $tutor);
        $this->plan($contract, [$this->item('2026-09-28')]);
        Setting::setValue('coaching', 'enabled', false);
        $dashboard = app(CoachingParticipantDashboard::class)->build($user);
        $this->assertSame([], $dashboard['contracts']);
        $this->assertSame(0, $dashboard['total_units']);
        $this->assertNull($dashboard['next_session']);
        Setting::setValue('coaching', 'enabled', true);
        $participant->delete();
        $this->assertSame([], app(CoachingParticipantDashboard::class)->build($user)['contracts']);
    }

    public function test_unreleased_course_and_invalid_schedule_items_are_never_presented_as_ready_appointments(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        $contract = $this->contract($participant, $tutor);
        $this->plan($contract, [$this->item('2026-09-28')]);
        $contract->course->update(['is_active' => false]);
        $second = $this->contract($participant, $tutor, ['valid_from' => '2026-09-25', 'valid_until' => '2026-09-30']);
        $this->plan($second, [$this->item('2026-09-24'), $this->item('2026-10-01'), $this->item('2026-09-31'),
            $this->item('not-a-date'), $this->item('2026-09-28', '12:00', '11:00'), $this->item('2026-09-28', '09:00', '10:30', 'unknown')]);
        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame('review', $dashboard['contracts'][1]['status']);
        $this->assertSame(0, $dashboard['upcoming_count']);
        $this->assertNull($dashboard['next_session']);
    }

    public function test_missing_tutor_never_looks_ready_and_unassigned_draft_is_not_an_actionable_plan(): void
    {
        $participant = $this->person(); $tutor = $this->person(null, 'tutor');
        $released = $this->contract($participant, $tutor);
        $this->plan($released, [$this->item('2026-09-28')]);
        $this->contract($participant, $tutor, ['contract_status' => 'draft', 'tutor_person_id' => null]);
        $tutor->delete();

        $dashboard = app(CoachingParticipantDashboard::class)->build($participant->user);
        $this->assertSame(['waiting', 'review'], array_column($dashboard['contracts'], 'status'));
        $this->assertSame(0, $dashboard['ready_count']);
        $this->assertSame(0, $dashboard['planning_count']);
        $this->assertSame(1, $dashboard['waiting_count']);
        $this->assertSame(0, $dashboard['upcoming_count']);
    }

    public function test_tutor_dashboard_only_shows_assigned_coachings_and_a_released_upcoming_session(): void
    {
        $tutor = $this->person(null, 'tutor'); $participant = $this->person();
        $draft = $this->contract($participant, $tutor, ['contract_status' => 'draft']);
        $released = $this->contract($participant, $tutor, ['contract_status' => 'active']);
        $this->plan($released, [$this->item('2026-09-28')]);
        $foreignTutor = $this->person(null, 'tutor');
        $this->contract($participant, $foreignTutor);

        $dashboard = app(CoachingTutorDashboard::class)->build($tutor->user);

        $this->assertSame([$released->id, $draft->id], array_column($dashboard['contracts'], 'id'));
        $this->assertSame(1, $dashboard['open_count']);
        $this->assertSame(1, $dashboard['ready_count']);
        $this->assertSame('2026-09-28', $dashboard['next_session']['date']);
        $this->assertSame(route('coaching.planning', ['contract' => $draft->id]), $dashboard['contracts'][1]['url']);
    }

    public function test_tutor_dashboard_hides_disabled_coaching_and_unreleased_appointments(): void
    {
        $tutor = $this->person(null, 'tutor'); $participant = $this->person();
        $contract = $this->contract($participant, $tutor, ['contract_status' => 'active']);
        $this->plan($contract, [$this->item('2026-09-28')]);
        $contract->course->update(['is_active' => false]);
        $this->assertNull(app(CoachingTutorDashboard::class)->build($tutor->user)['next_session']);

        Setting::setValue('coaching', 'enabled', false);
        $this->assertSame([], app(CoachingTutorDashboard::class)->build($tutor->user)['contracts']);
    }
}
