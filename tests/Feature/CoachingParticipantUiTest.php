<?php

namespace Tests\Feature;

use App\Livewire\User\Program\Course\CourseShowOverview;
use App\Livewire\User\ProgramShow;
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
            '2025_10_05_104501_create_course_ratings_table.php', '2025_10_23_180357_create_course_material_acknowledgements_table.php'] as $file) {
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
