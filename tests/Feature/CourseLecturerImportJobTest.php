<?php

namespace Tests\Feature;

use App\Jobs\ApiUpdates\CreateOrUpdateCourse;
use App\Models\{Course, CourseDay, Person, User};
use App\Services\ApiUvs\ApiUvsService;
use App\Services\Courses\CourseLecturerAccess as Access;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, Cache, DB, Event, Http, Mail, Queue, Schema, Storage};
use Tests\TestCase;

class CourseLecturerImportJobTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app=require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,function($app){
            $app['config']->set(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
                'cache.default'=>'array','queue.default'=>'sync','mail.default'=>'array','session.driver'=>'array','activitylog.dispatch_enabled'=>false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();DB::purge('sqlite');Bus::fake();Queue::fake();Mail::fake();Http::preventStrayRequests();Storage::fake('private');
        $this->travelTo(Carbon::parse('2026-11-06 10:00','Europe/Berlin'));
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('role')->default('tutor');$t->string('password')->nullable();$t->timestamps();});
        foreach([
            '2025_09_10_152937_create_persons_table.php','2025_11_28_005941_add_teilnehmer_fields_to_persons_table.php','2026_02_07_120000_add_deleted_at_to_persons_table.php',
            '2025_09_10_152938_create_courses_table.php','2025_10_23_172321_add_educational_materials_to_courses_table.php','2026_02_16_130000_add_sync_status_to_courses_table.php',
            '2025_09_10_152939_create_course_days_table.php','2025_11_23_194819_add_note_status_and_settings_to_course_days_table.php','2025_11_26_115101_add_attendance_sync_columns_to_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php','2025_10_13_050722_create_course_results_table.php',
        ]as$file){(require database_path('migrations/'.$file))->up();}
        // MySQL DATE truncates Eloquent's midnight datetime on storage. SQLite's
        // text affinity does not; reproduce the production DATE semantics here.
        DB::unprepared('CREATE TRIGGER normalize_import_day_date AFTER INSERT ON course_days BEGIN UPDATE course_days SET date = date(NEW.date) WHERE id = NEW.id; END');
        Schema::create('files',function(Blueprint $t){$t->id();$t->morphs('fileable');$t->integer('user_id');$t->string('type');$t->string('path');$t->timestamps();});
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        (require database_path('migrations/2026_11_06_120001_add_lecturer_evidence_columns.php'))->up();
        DB::table('users')->insert(['id'=>1,'name'=>'Anna Alt','email'=>'alt@example.test','role'=>'tutor']);
        DB::table('persons')->insert([
            ['id'=>11,'user_id'=>1,'person_id'=>'1-0000001','institut_id'=>'1','person_nr'=>'0000001','vorname'=>'Anna','nachname'=>'Alt','teilnehmer_id'=>null,'statusdata'=>json_encode(['is_tutor'=>true,'mitarbeiter_id'=>'MA-A'])],
            ['id'=>33,'user_id'=>null,'person_id'=>'1-0000033','institut_id'=>'1','person_nr'=>'0000033','vorname'=>'Test','nachname'=>'Teilnehmer','teilnehmer_id'=>'TN-33','statusdata'=>null],
        ]);
    }

    protected function tearDown():void{$this->travelBack();parent::tearDown();}

    /** Exactly CourseApiController's body, wrapped as ApiUvsService::request returns it. */
    private function response(int $revision=2,bool $managed=true):array
    {
        $teachers=[];
        foreach([[1,'Anna','Alt'],[2,'Ben','Neu'],[3,'Clara','Zukunft']]as[$id,$first,$last]){
            $teachers[]=['person_id'=>'1-000000'.$id,'institut_id'=>'1','person_nr'=>'000000'.$id,'status'=>'0','vorname'=>$first,'nachname'=>$last,
                'email_priv'=>null,'email_cbw'=>null,'mitarbeiter_id'=>'MA-'.chr(64+$id),'klassen_id'=>'26T01-1-BMFK'];
        }
        $assignments=[];
        foreach([[1,'a','2026-10-19','2026-11-04'],[2,'b','2026-11-05','2026-11-06'],[3,'c','2026-11-07','2026-11-08']]as[$person,$id,$start,$end]){
            $assignments[]=['assignment_id'=>str_repeat($id,32),'person_id'=>'1-000000'.$person,'mitarbeiter_id'=>'MA-'.chr(64+$person),'contract_id'=>(string)$person,
                'legacy_assignment_uid'=>(string)$person,'starts_on'=>$start,'ends_on'=>$end,'name'=>$teachers[$person-1]['vorname'].' '.$teachers[$person-1]['nachname'],
                'reason'=>'Organisatorische Übergabe','changed_by'=>7,'changed_at'=>'2026-11-01T10:00:00+00:00'];
        }
        $plan=['version'=>1,'revision'=>$revision,'status'=>'ready','assignments'=>$assignments,'contracts'=>[],
            'completion_at'=>'2026-11-06T16:00:00+01:00','closing_assignment_id'=>str_repeat('b',32),'current_assignment_id'=>str_repeat('b',32),
            'history'=>[['revision'=>$revision,'reason'=>'Organisatorische Übergabe','changed_by'=>7,'changed_at'=>'2026-11-01T10:00:00+00:00']],'conflicts'=>[]];
        $payload=[
            'course'=>['klassen_id'=>'26T01-1-BMFK','termin_id'=>'26T01','kurzbez'=>'DLO1','bezeichnung'=>'DLO1 Beispielkurs','beginn'=>'2026/10/19','ende'=>'2026/11/08',
                'institut_id_ks'=>'1','vtz_kennz_ks'=>'TZ','klassen_co_ks'=>'BMFK','status'=>'0','unterr_raum'=>'Online','unterr_beginn'=>'08:00','unterr_ende'=>'16:00','unterr_ende_fr'=>'16:00','unterr_ende_fr2'=>''],
            'participants'=>[['person_id'=>'1-0000033','institut_id'=>'1','person_nr'=>'0000033','status'=>'0','vorname'=>'Test','nachname'=>'Teilnehmer',
                'klassen_id'=>'26T01-1-BMFK','teilnehmer_id'=>'TN-33','tn_baustein_id'=>'TN-33-DLO1','baustein_id'=>'DLO1']],
            'teachers'=>$managed?$teachers:[$teachers[0]],
            'days'=>array_map(fn($date)=>['termtag_id'=>'26T01-'.$date,'termin_id'=>'26T01','institut_id'=>'1','datum'=>$date,'wochentag'=>'','std'=>'8.00','unterr_beginn'=>'08:00','unterr_ende'=>'16:00','art'=>'B'],['2026/11/04','2026/11/06']),
            'materials'=>[['titel'=>'Lernmaterial','titel2'=>'','verlag'=>'Beispiel','isbn'=>'','isbn_pdf'=>'','bestell_nr'=>'','preis'=>'0.00','preis_pdf'=>'0.00']],
            'lecturer_plan'=>$managed?$plan:['version'=>1,'revision'=>0,'status'=>'legacy','assignments'=>[],'contracts'=>[],'completion_at'=>'2026-11-06T16:00:00+01:00','closing_assignment_id'=>null,'current_assignment_id'=>null,'history'=>[]],
        ];
        return ['ok'=>true,'status'=>200,'data'=>['ok'=>true,'data'=>$payload]];
    }

    private function api(array ...$responses):void
    {
        $api=\Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getCourseByKlassenId')->with('26T01-1-BMFK')->times(count($responses))->andReturn(...$responses);
        app()->instance(ApiUvsService::class,$api);
    }

    public function test_actual_import_job_adds_missing_current_and_future_teachers_and_preserves_course_evidence():void
    {
        $course=Course::withoutEvents(fn()=>Course::create(['title'=>'Bisheriger Kurs','klassen_id'=>'26T01-1-BMFK','termin_id'=>'26T01',
            'planned_start_date'=>'2026-10-19','planned_end_date'=>'2026-11-08','primary_tutor_person_id'=>11]));
        DB::table('course_days')->insert(['id'=>9,'course_id'=>$course->id,'date'=>'2026-11-04','start_time'=>'09:00','end_time'=>'15:00','std'=>6,
            'notes'=>'Unveränderter Unterrichtsnachweis','note_status'=>2,'notes_author_user_id'=>1,'notes_assignment_id'=>str_repeat('a',32),'notes_revision'=>3,
            'day_sessions'=>json_encode([['id'=>'session-original','notes'=>'Vorhandener Inhalt']]),
            'attendance_data'=>json_encode(['participants'=>[33=>['present'=>false,'state'=>'synced','updated_by'=>1,'lecturer_assignment_id'=>str_repeat('a',32),'lecturer_plan_revision'=>1]]])]);
        DB::table('course_participant_enrollments')->insert(['course_id'=>$course->id,'person_id'=>33,'teilnehmer_id'=>'TN-33','results'=>'{"legacy":82}','notes'=>'["vorhandene Notiz"]']);
        DB::table('course_results')->insert(['course_id'=>$course->id,'person_id'=>33,'result'=>'82','status'=>'Bestanden','updated_by'=>1,'lecturer_assignment_id'=>str_repeat('a',32),'lecturer_plan_revision'=>1]);
        Storage::disk('private')->put('signatures/unchanged.svg','<svg>Originalunterschrift</svg>');
        DB::table('files')->insert(['fileable_type'=>CourseDay::class,'fileable_id'=>9,'user_id'=>1,'type'=>'sign_courseday_doku_tutor','path'=>'signatures/unchanged.svg',
            'lecturer_person_id'=>11,'lecturer_assignment_id'=>str_repeat('a',32),'document_revision'=>3]);
        $beforeDay=DB::table('course_days')->where('id',9)->first();$beforeResult=DB::table('course_results')->first();$beforeSignature=DB::table('files')->first();
        $savesInSystem=0;
        Event::listen('eloquent.saving: '.CourseDay::class,function()use(&$savesInSystem){$this->assertTrue(Access::isSystemSync());$this->assertGuest();$savesInSystem++;});
        $this->api($this->response());(new CreateOrUpdateCourse('26T01-1-BMFK'))->handle();
        $course=$course->fresh();$current=Person::where('person_id','1-0000002')->sole();$future=Person::where('person_id','1-0000003')->sole();
        $this->assertSame('ready',$course->lecturer_plan['status']);$this->assertSame(2,(int)$course->lecturer_plan_revision);
        $this->assertSame((int)$current->id,(int)$course->getRawOriginal('primary_tutor_person_id'));$this->assertSame('Ben',$course->tutor->vorname);
        $this->assertSame(1,Person::where('person_id','1-0000001')->count());$this->assertSame(1,User::count());
        foreach([Person::find(11),$current,$future]as$person){$this->assertSame([$course->id],$person->taughtCourses()->pluck('id')->all());}
        $this->assertTrue(Access::canView(User::find(1),$course));$this->assertFalse(Access::canManageCourse(User::find(1),$course));
        $afterDay=DB::table('course_days')->where('id',9)->first();
        foreach(['notes','note_status','notes_author_user_id','notes_assignment_id','notes_revision','attendance_data','day_sessions']as$field){$this->assertSame($beforeDay->$field,$afterDay->$field);}
        $this->assertEquals($beforeResult,DB::table('course_results')->first());$this->assertEquals($beforeSignature,DB::table('files')->first());
        $this->assertSame('<svg>Originalunterschrift</svg>',Storage::disk('private')->get('signatures/unchanged.svg'));
        $this->assertSame(2,$course->days()->count());$this->assertGreaterThanOrEqual(2,$savesInSystem);
        $this->assertSame(['legacy'=>82],$course->enrollments()->sole()->results);$this->assertFalse(Access::isSystemSync());Mail::assertNothingSent();
    }

    public function test_cooldown_stays_twenty_minutes_and_late_older_revision_cannot_reset_assignment_or_primary():void
    {
        $older=$this->response(1);$oldPlan=&$older['data']['data']['lecturer_plan'];
        $oldPlan['assignments']=[array_replace($oldPlan['assignments'][0],['ends_on'=>'2026-11-08'])];$oldPlan['closing_assignment_id']=str_repeat('a',32);
        $older['data']['data']['teachers']=[$older['data']['data']['teachers'][0]];
        $this->api($this->response(2),$older);
        (new CreateOrUpdateCourse('26T01-1-BMFK'))->handle();$course=Course::sole();$before=$course->lecturer_plan;$primary=$course->getRawOriginal('primary_tutor_person_id');
        $cooldown=Cache::get('course-sync-cooldown:26T01-1-BMFK');$this->assertNotNull($cooldown);
        $this->travelTo(Carbon::parse('2026-11-06 10:19:59','Europe/Berlin'));(new CreateOrUpdateCourse('26T01-1-BMFK'))->handle();
        $this->assertSame($cooldown,Cache::get('course-sync-cooldown:26T01-1-BMFK'));
        $this->travelTo(Carbon::parse('2026-11-06 10:20:01','Europe/Berlin'));(new CreateOrUpdateCourse('26T01-1-BMFK'))->handle();
        $course=$course->fresh();$this->assertSame(2,(int)$course->lecturer_plan_revision);$this->assertSame($before,$course->lecturer_plan);
        $this->assertSame($primary,$course->getRawOriginal('primary_tutor_person_id'));$this->assertSame('Ben',$course->tutor->vorname);
        $this->assertSame(3,count(Access::assignments($course)));$this->assertFalse(Access::isSystemSync());
    }

    public function test_legacy_import_keeps_existing_single_primary_teacher_structure():void
    {
        $this->api($this->response(0,false));(new CreateOrUpdateCourse('26T01-1-BMFK'))->handle();$course=Course::sole();
        $this->assertFalse(Access::hasPlan($course));$this->assertSame(11,(int)$course->getRawOriginal('primary_tutor_person_id'));
        $this->assertSame('Anna',$course->tutor->vorname);$this->assertSame(1,Person::where('role','tutor')->count());
        $this->assertSame(2,$course->days()->count());$this->assertFalse(Access::isSystemSync());Mail::assertNothingSent();
    }
}
