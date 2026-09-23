<?php

namespace Tests\Feature;

use App\Livewire\User\Program\Course\CourseShowOverview;
use App\Livewire\User\ProgramShow;
use App\Livewire\Coaching\Planning;
use App\Services\Coaching\Access;
use App\Models\{CoachingContract, Course, CourseDay, CourseParticipantEnrollment, Person, Setting, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{Blade, Queue, Schema};
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CoachingParticipantUiTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
                'cache.default'=>'array', 'queue.default'=>'sync', 'session.driver'=>'array']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('role'); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('person_id'); $t->integer('institut_id'); $t->string('role'); $t->json('programdata')->nullable(); $t->json('statusdata')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        foreach (['2025_09_10_152938_create_courses_table.php', '2025_09_10_152939_create_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php', '2026_09_17_080000_create_coaching_planning_tables.php',
            '2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php', '2026_09_22_100000_create_coaching_notices.php',
            '2025_10_05_104501_create_course_ratings_table.php', '2025_10_23_180357_create_course_material_acknowledgements_table.php',
            '2025_08_16_205311_create_file_pools_table.php', '2025_08_16_205324_create_files_table.php',
            '2026_01_15_081041_create_onboarding_videos_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('course_days', function (Blueprint $t) { $t->integer('note_status')->default(0); $t->json('settings')->nullable(); });
        Setting::setValue('coaching', 'enabled', true);
    }

    private function participant(): Person
    {
        $user = User::create(['name'=>'UI Test', 'role'=>'guest']);
        $this->actingAs($user);
        return Person::withoutEvents(fn () => Person::create(['user_id'=>$user->id, 'person_id'=>'1-'.$user->id, 'institut_id'=>1, 'role'=>'guest']));
    }

    private function contract(Person $person, int $id): CoachingContract
    {
        return CoachingContract::create(['uuid'=>(string) Str::uuid(), 'uvs_contract_id'=>$id, 'institut_id'=>1,
            'uvs_person_id'=>$person->person_id, 'participant_person_id'=>$person->id, 'beratung_id'=>'ui-'.$id,
            'title'=>'Coaching '.$id, 'agreed_minutes'=>180, 'unit_minutes'=>45, 'contract_version'=>str_repeat('a',64)]);
    }

    private function navigation(): string
    {
        return view('livewire.user-navigation-menu', ['currentUrl' => url('/user/messages')])->render();
    }

    private function assertPlanningNotFound(callable $action): void
    {
        $this->withoutExceptionHandling();
        try {
            $action();
            $this->fail('Planning was accessible without an assignment.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_ordinary_participant_keeps_navigation_without_coaching_and_cannot_open_planning(): void
    {
        $other = $this->participant();
        $this->contract($other, 1);
        $ordinary = $this->participant();
        $ordinary->update(['programdata' => ['vtz' => 'V', 'tn_baust' => array_fill(0, 20, [])]]);
        auth()->user()->unsetRelation('person');

        $this->assertFalse(Access::canUsePlanning(auth()->user()));
        $html = $this->navigation();
        foreach (['Konto', 'Berichtsheft', 'Anträge', 'Videos'] as $label) $this->assertStringContainsString($label, $html);
        $this->assertStringNotContainsString(route('coaching.planning'), $html);
        $this->assertStringNotContainsString('Einzelcoaching', Blade::render('<x-ui.coaching-modules />'));
        $this->assertPlanningNotFound(fn () => Livewire::test(Planning::class));
    }

    public function test_explicit_draft_without_course_or_plan_already_grants_coaching_navigation(): void
    {
        $person = $this->participant();
        $contract = $this->contract($person, 1);
        $contract->update(['contract_status' => 'draft']);

        $this->assertTrue(Access::canUsePlanning(auth()->user()));
        $this->assertStringContainsString(route('coaching.planning'), $this->navigation());
        Livewire::test(Planning::class)->assertOk()->assertSee($contract->title);
    }

    public function test_legacy_labels_and_cached_status_alone_do_not_grant_new_planning_access(): void
    {
        $person = $this->participant();
        $person->update(['programdata' => ['vtz' => 'E'],
            'statusdata' => ['coaching_contracts' => [['status' => 'active']]]]);

        $this->assertFalse(Access::canUsePlanning(auth()->user()));
        $this->assertStringNotContainsString(route('coaching.planning'), $this->navigation());
        $this->assertPlanningNotFound(fn () => Livewire::test(Planning::class));
    }

    public function test_disabled_feature_and_removed_assignment_revoke_navigation_and_livewire_access(): void
    {
        $person = $this->participant();
        $contract = $this->contract($person, 1);
        $page = Livewire::test(Planning::class)->assertOk();
        $contract->update(['participant_person_id' => null]);
        $this->assertFalse(Access::canUsePlanning(auth()->user()));
        $this->assertStringNotContainsString(route('coaching.planning'), $this->navigation());
        $this->assertPlanningNotFound(fn () => $page->instance()->render());

        $contract->update(['participant_person_id' => $person->id]);
        Setting::setValue('coaching', 'enabled', false);
        $this->assertFalse(Access::canUsePlanning(auth()->user()));
        $this->assertStringNotContainsString(route('coaching.planning'), $this->navigation());
        $this->assertPlanningNotFound(fn () => Livewire::test(Planning::class));
    }

    public function test_tutor_navigation_requires_an_explicit_assignment_too(): void
    {
        $participant = $this->participant();
        $contract = $this->contract($participant, 1);
        $tutor = $this->participant();
        auth()->user()->update(['role' => 'tutor']);
        $this->assertStringNotContainsString(route('coaching.planning'), $this->navigation());
        $this->assertStringNotContainsString(route('coaching.planning'), view('layouts.sidebar')->render());
        $contract->update(['tutor_person_id' => $tutor->id]);
        $this->assertStringContainsString(route('coaching.planning'), $this->navigation());
        $this->assertStringContainsString(route('coaching.planning'), view('layouts.sidebar')->render());
        Livewire::test(Planning::class)->assertOk();
    }

    public function test_deleted_person_and_anonymous_user_have_no_coaching_navigation_access(): void
    {
        $person = $this->participant();
        $this->contract($person, 1);
        auth()->user()->load('persons');
        Person::withoutEvents(fn () => $person->delete());
        $this->assertFalse(Access::canUsePlanning(auth()->user()));
        $this->assertFalse(Access::canUsePlanning(null));
    }

    public function test_participant_overview_shows_actual_units_including_fractional_units(): void
    {
        $person = $this->participant();
        $contract = $this->contract($person, 1);
        $course = Course::create(['title'=>'Coaching', 'klassen_id'=>'ec-ui', 'type'=>'coaching', 'vtz'=>'E', 'institut_id'=>1]);
        $contract->update(['course_id'=>$course->id]);
        CourseParticipantEnrollment::create(['person_id'=>$person->id, 'course_id'=>$course->id, 'klassen_id'=>'ec-ui', 'status'=>'active', 'is_active'=>true]);
        foreach (['2026-09-28'=>2, '2026-09-30'=>1.5] as $date=>$units) {
            CourseDay::create(['course_id'=>$course->id, 'type'=>'coaching', 'date'=>$date, 'std'=>$units]);
        }
        Livewire::test(CourseShowOverview::class, ['klassenId'=>'ec-ui'])->assertSet('stats.einheiten', 3.5)->assertSee('3.5');
        $this->participant();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CourseShowOverview())->mount('ec-ui');
    }

    public function test_each_dashboard_planning_link_addresses_its_own_contract(): void
    {
        $person = $this->participant();
        $first = $this->contract($person, 1); $second = $this->contract($person, 2);
        $html = Blade::render('<x-ui.coaching-modules />');
        $this->assertStringContainsString(route('coaching.planning', ['contract'=>$first->id]), $html);
        $this->assertStringContainsString(route('coaching.planning', ['contract'=>$second->id]), $html);
        $first->update(['contract_status'=>'cancelled']);
        $this->assertStringContainsString('Terminplan ansehen', Blade::render('<x-ui.coaching-modules />'));
    }

    public function test_coaching_dashboard_renders_even_with_legacy_coaching_program_data(): void
    {
        $person = $this->participant();
        $person->update(['programdata' => ['vtz' => 'E', 'langbez_m' => 'Legacy Coaching']]);
        $this->contract($person, 1);

        Livewire::test(ProgramShow::class)
            ->assertSet('coachingOnly', true)->assertSet('apiProgramLoading', false)
            ->assertSee('Mein Einzelcoaching')->assertSee('Dein nächster Termin')
            ->assertSee('Fest vereinbarte Termine')->assertSee('Offene Abstimmungen')
            ->assertSee('Meine Coaching-Bausteine')->assertSee('4 UE')
            ->assertDontSee('Berichtsheft')->assertDontSee('Programm Daten werden geladen');
    }

    public function test_parallel_ordinary_program_is_preferred_to_legacy_coaching_program(): void
    {
        $person = $this->participant();
        $person->update(['programdata' => ['vtz' => 'E']]);
        $this->contract($person, 1);
        $ordinary = Person::withoutEvents(fn () => Person::create([
            'user_id' => $person->user_id, 'person_id' => '1-ordinary', 'institut_id' => 1,
            'role' => 'guest', 'programdata' => ['vtz' => 'V'],
        ]));
        $component = new ProgramShow;
        $component->userData = auth()->user()->fresh()->load('persons');
        $method = new \ReflectionMethod(ProgramShow::class, 'resolveProgramPerson');
        $method->setAccessible(true);
        $this->assertSame($ordinary->id, $method->invoke($component)->id);
    }
}
