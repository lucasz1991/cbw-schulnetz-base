<?php

namespace Tests\Feature;

use App\Models\{Course, Mail};
use App\Services\Courses\{CourseLecturerAccess as Access, LecturerPlanProjector};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Artisan, Bus, DB, Http, Mail as Mailer, Queue, Schema};
use Tests\TestCase;

class CourseLecturerReminderTest extends TestCase
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
        parent::setUp();DB::purge('sqlite');Bus::fake();Queue::fake();Mailer::fake();Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-11-06 09:30','Europe/Berlin'));
        Schema::create('settings',function(Blueprint $t){$t->id();$t->string('type');$t->string('key');$t->text('value')->nullable();$t->timestamps();});
        Schema::create('persons',function(Blueprint $t){
            $t->id();$t->integer('user_id')->nullable();$t->string('person_id');$t->string('email_priv')->nullable();$t->string('email_cbw')->nullable();$t->softDeletes();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('course_days',function(Blueprint $t){
            $t->id();$t->integer('course_id');$t->date('date');$t->time('end_time')->nullable();$t->integer('note_status')->default(0);$t->json('attendance_data')->nullable();$t->timestamps();$t->softDeletes();
        });
        Schema::create('course_participant_enrollments',function(Blueprint $t){
            $t->id();$t->integer('course_id');$t->integer('person_id');$t->boolean('is_active')->default(true);$t->softDeletes();
            foreach(['teilnehmer_id','tn_baustein_id','baustein_id','klassen_id','termin_id','vtz','kurzbez_ba','status','results','notes','source_snapshot','source_last_upd','last_synced_at'] as $field){$t->text($field)->nullable();}
        });
        Schema::create('course_results',function(Blueprint $t){$t->id();$t->integer('course_id');$t->integer('person_id');$t->integer('result')->nullable();$t->string('status')->nullable();});
        Schema::create('mails',function(Blueprint $t){$t->id();$t->string('type');$t->boolean('status')->default(false);$t->json('content');$t->json('recipients');$t->timestamps();});
        Schema::create('files',function(Blueprint $t){$t->id();$t->morphs('fileable');$t->string('type')->nullable();$t->timestamps();$t->softDeletes();});
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        DB::table('persons')->insert([
            ['id'=>11,'user_id'=>1,'person_id'=>'UVS-A','email_cbw'=>'alt@example.test'],
            ['id'=>22,'user_id'=>2,'person_id'=>'UVS-B','email_cbw'=>'neu@example.test'],
            ['id'=>33,'user_id'=>null,'person_id'=>'UVS-T','email_cbw'=>null],
        ]);
    }

    protected function tearDown():void{$this->travelBack();parent::tearDown();}

    private function source(int $revision=1):array
    {
        return ['version'=>1,'revision'=>$revision,'status'=>'ready','completion_at'=>'2026-11-06T16:00:00+01:00','closing_assignment_id'=>'B',
            'assignments'=>[
                ['assignment_id'=>'A','person_id'=>'UVS-A','mitarbeiter_id'=>'MA-A','contract_id'=>'101','legacy_assignment_uid'=>'101','starts_on'=>'2026-10-19','ends_on'=>'2026-11-04'],
                ['assignment_id'=>'B','person_id'=>'UVS-B','mitarbeiter_id'=>'MA-B','contract_id'=>'202','legacy_assignment_uid'=>'202','starts_on'=>'2026-11-05','ends_on'=>'2026-11-06'],
                ['assignment_id'=>'C','person_id'=>'UVS-A','mitarbeiter_id'=>'MA-A','contract_id'=>'101','legacy_assignment_uid'=>'101','starts_on'=>'2026-11-07','ends_on'=>'2026-11-08'],
            ]];
    }

    private function course(array $settings=[]):Course
    {
        $course=Course::withoutEvents(fn()=>Course::create(['title'=>'DLO1','klassen_id'=>'BMFK','termin_id'=>'T-1',
            'planned_start_date'=>'2026-10-19','planned_end_date'=>'2026-11-08','primary_tutor_person_id'=>11,'settings'=>$settings]));
        DB::table('course_participant_enrollments')->insert(['course_id'=>$course->id,'person_id'=>33]);
        LecturerPlanProjector::apply($course,$this->source());return $course;
    }

    private function enable(string ...$keys):void
    {
        foreach($keys as $key){DB::table('settings')->insert(['type'=>'mails','key'=>$key,'value'=>'true']);}
    }

    public function test_successor_documentation_reminder_contains_only_own_days_and_gets_own_reminder_limit():void
    {
        $this->enable('lecturer_module_documentation_missing');
        $course=$this->course(['reminder_state_lecturer_module_documentation_missing_A'=>['count'=>2,'last_sent_at'=>'2026-11-04T10:00:00+01:00']]);
        DB::table('course_days')->insert([
            ['course_id'=>$course->id,'date'=>'2026-11-04','note_status'=>0],
            ['course_id'=>$course->id,'date'=>'2026-11-05','note_status'=>2],
        ]);
        $this->assertSame(0,Artisan::call('courses:dispatch-lecturer-reminders'));$this->assertSame(0,Mail::count());
        DB::table('course_days')->whereDate('date','2026-11-05')->update(['note_status'=>0]);
        Artisan::call('courses:dispatch-lecturer-reminders');Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(1,Mail::count());$mail=Mail::sole();
        $this->assertStringContainsString('1 Tageseinträge',$mail->content['body']);
        $this->assertSame([2],array_values(array_filter(array_column($mail->recipients,'user_id'))));
        $this->assertSame(['neu@example.test'],array_values(array_filter(array_column($mail->recipients,'email'))));
        $this->assertSame(1,$course->fresh()->getSetting('reminder_state_lecturer_module_documentation_missing_B')['count']);
        $this->assertSame(2,$course->fresh()->getSetting('reminder_state_lecturer_module_documentation_missing_A')['count']);
        Mailer::assertNothingSent();
    }

    public function test_successor_attendance_reminder_does_not_include_former_lecturer_days():void
    {
        $this->enable('attendance_proof_missing');$course=$this->course();
        DB::table('course_days')->insert([
            ['course_id'=>$course->id,'date'=>'2026-11-04','attendance_data'=>json_encode(['participants'=>[]])],
            ['course_id'=>$course->id,'date'=>'2026-11-05','attendance_data'=>json_encode(['participants'=>[33=>['present'=>true]]])],
        ]);
        Artisan::call('courses:dispatch-lecturer-reminders');$this->assertSame(0,Mail::count());
        DB::table('course_days')->whereDate('date','2026-11-05')->update(['attendance_data'=>json_encode(['participants'=>[]])]);
        Artisan::call('courses:dispatch-lecturer-reminders');Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(1,Mail::count());$this->assertSame(2,Mail::sole()->recipients[0]['user_id']);
        $this->assertSame(1,$course->fresh()->getSetting('reminder_state_attendance_proof_missing_B')['count']);
        $this->assertNull($course->fresh()->getSetting('reminder_state_attendance_proof_missing_A'));
    }

    public function test_results_reminder_goes_to_actual_closing_lecturer_even_when_calendar_last_lecturer_differs():void
    {
        $this->enable('exam_results_missing_by_lecturer');$course=$this->course();
        $this->travelTo(Carbon::parse('2026-11-21 09:30','Europe/Berlin'));
        $this->assertSame(11,Access::currentPersonId($course));$this->assertSame(22,Access::closingAssignment($course)['person_id']);
        Artisan::call('courses:dispatch-lecturer-reminders');Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(1,Mail::count());$this->assertSame(2,Mail::sole()->recipients[0]['user_id']);
        $this->assertSame(['neu@example.test'],array_values(array_filter(array_column(Mail::sole()->recipients,'email'))));
        $this->assertSame(1,$course->fresh()->getSetting('reminder_state_exam_results_missing_by_lecturer_B')['count']);
        $this->assertNull($course->fresh()->getSetting('reminder_state_exam_results_missing_by_lecturer_C'));
        Mailer::assertNothingSent();
    }

    public function test_conflicting_plan_creates_no_reminder_and_consumes_no_assignment_state():void
    {
        $this->enable('lecturer_module_documentation_missing','attendance_proof_missing','exam_results_missing_by_lecturer');
        $course=$this->course();DB::table('course_days')->insert(['course_id'=>$course->id,'date'=>'2026-11-05','note_status'=>0,'attendance_data'=>'{"participants":{}}']);
        $source=$this->source(2);$source['assignments'][1]['starts_on']='2026-11-06';LecturerPlanProjector::apply($course,$source);
        $this->assertFalse(Access::healthy($course));Artisan::call('courses:dispatch-lecturer-reminders');
        $this->travelTo(Carbon::parse('2026-11-21 09:30','Europe/Berlin'));Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(0,Mail::count());$this->assertSame([],array_filter($course->fresh()->settings??[],fn($key)=>str_starts_with($key,'reminder_state_'),ARRAY_FILTER_USE_KEY));
        Mailer::assertNothingSent();
    }
}
