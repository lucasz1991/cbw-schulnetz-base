<?php

namespace Tests\Feature;

use App\Models\{Course, CourseDay, User};
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;
use App\Services\Courses\{CourseLecturerAccess as Access, LecturerPlanProjector};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail, Queue, Schema};
use Tests\TestCase;

class CourseLecturerAttendanceSyncTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:',
                'cache.default'=>'array','queue.default'=>'sync','mail.default'=>'array','session.driver'=>'array','activitylog.dispatch_enabled'=>false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp(); DB::purge('sqlite'); Bus::fake(); Queue::fake(); Mail::fake(); Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-11-06 10:00','Europe/Berlin'));
        Schema::create('users',function (Blueprint $t) { $t->id();$t->string('name')->nullable();$t->string('email')->nullable();$t->string('password')->nullable();$t->timestamps(); });
        Schema::create('persons',function (Blueprint $t) {
            $t->id();$t->integer('user_id')->nullable();$t->integer('institut_id')->default(1);
            foreach (['person_id','teilnehmer_id','vorname','nachname'] as $field) { $t->string($field)->nullable(); }
            $t->timestamps();$t->softDeletes();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('course_days',function (Blueprint $t) {
            $t->id();$t->integer('course_id');$t->date('date');$t->time('start_time')->nullable();$t->time('end_time')->nullable();$t->decimal('std')->default(8);
            $t->json('day_sessions')->nullable();$t->json('attendance_data')->nullable();$t->dateTime('attendance_updated_at')->nullable();$t->dateTime('attendance_last_synced_at')->nullable();
            $t->timestamps();$t->softDeletes();
        });
        Schema::create('course_participant_enrollments',function (Blueprint $t) {
            $t->id();$t->integer('course_id');$t->integer('person_id');$t->boolean('is_active')->default(true);$t->softDeletes();
            foreach (['teilnehmer_id','tn_baustein_id','baustein_id','klassen_id','termin_id','vtz','kurzbez_ba','status','results','notes','source_snapshot','source_last_upd','last_synced_at'] as $field) { $t->text($field)->nullable(); }
        });
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        DB::table('users')->insert([['id'=>1,'name'=>'Alt'],['id'=>2,'name'=>'Neu']]);
        DB::table('persons')->insert([
            ['id'=>11,'user_id'=>1,'person_id'=>'UVS-A','teilnehmer_id'=>null,'vorname'=>'Alte','nachname'=>'Lehrkraft'],
            ['id'=>22,'user_id'=>2,'person_id'=>'UVS-B','teilnehmer_id'=>null,'vorname'=>'Neue','nachname'=>'Lehrkraft'],
            ['id'=>33,'user_id'=>null,'person_id'=>'UVS-T1','teilnehmer_id'=>'TN-33','vorname'=>'Test','nachname'=>'Eins'],
            ['id'=>44,'user_id'=>null,'person_id'=>'UVS-T2','teilnehmer_id'=>'TN-44','vorname'=>'Test','nachname'=>'Zwei'],
        ]);
    }

    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }

    private function day(string $date='2026-11-06'): CourseDay
    {
        $course=Course::withoutEvents(fn()=>Course::create(['title'=>'DLO1','klassen_id'=>'BMFK','termin_id'=>'T-1','institut_id'=>1,
            'planned_start_date'=>'2026-10-19','planned_end_date'=>'2026-11-08','primary_tutor_person_id'=>11]));
        LecturerPlanProjector::apply($course,['version'=>1,'revision'=>2,'status'=>'ready','completion_at'=>'2026-11-06T16:00:00+01:00','closing_assignment_id'=>'B',
            'assignments'=>[
                ['assignment_id'=>'A','person_id'=>'UVS-A','mitarbeiter_id'=>'MA-A','contract_id'=>'101','legacy_assignment_uid'=>'101','starts_on'=>'2026-10-19','ends_on'=>'2026-11-04'],
                ['assignment_id'=>'B','person_id'=>'UVS-B','mitarbeiter_id'=>'MA-B','contract_id'=>'202','legacy_assignment_uid'=>'202','starts_on'=>'2026-11-05','ends_on'=>'2026-11-08'],
            ]]);
        foreach ([33,44] as $id) { DB::table('course_participant_enrollments')->insert(['course_id'=>$course->id,'person_id'=>$id]); }
        return CourseDay::withoutEvents(fn()=>CourseDay::create(['course_id'=>$course->id,'date'=>$date,'start_time'=>'08:00','end_time'=>'16:00','std'=>8,
            'attendance_data'=>['participants'=>[]]]));
    }

    public function test_saved_actor_assignment_and_revision_are_sent_by_the_actual_sync_service(): void
    {
        $day=$this->day();$this->actingAs(User::find(2));
        $day->setAttendance(33,['present'=>false,'excused'=>true,'state'=>'dirty']);
        $saved=$day->fresh()->attendance_data['participants'][33];
        $this->assertSame(2,$saved['updated_by']);$this->assertSame('B',$saved['lecturer_assignment_id']);$this->assertSame(2,$saved['lecturer_plan_revision']);
        $api=\Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('POST','/api/course/courseday/syncattendancedata',\Mockery::on(function ($payload) {
            $this->assertSame('BMFK',$payload['klassen_id']);$this->assertSame('MA-B',$payload['mitarbeiter_id']);
            $this->assertSame('B',$payload['lecturer_assignment_id']);$this->assertSame(2,$payload['lecturer_plan_revision']);
            $this->assertSame('2026-11-06',$payload['date']);$this->assertSame('TN-33',$payload['changes'][0]['teilnehmer_id']);
            return true;
        }),[])->andReturn(['ok'=>true,'data'=>['pulled'=>['items'=>[]],'pushed'=>['results'=>[]]]]);
        $this->assertTrue((new CourseDayAttendanceSyncService($api))->syncToRemote($day->fresh()));
        $this->assertSame('synced',$day->fresh()->attendance_data['participants'][33]['state']);
        $this->assertFalse(Access::isSystemSync());
    }

    public function test_stale_revision_and_former_actor_are_rejected_before_any_api_request(): void
    {
        $day=$this->day();$api=\Mockery::mock(ApiUvsService::class);$api->shouldReceive('request')->never();
        $invalid=[['updated_by'=>2,'lecturer_assignment_id'=>'B','lecturer_plan_revision'=>1],
            ['updated_by'=>1,'lecturer_assignment_id'=>'B','lecturer_plan_revision'=>2],
            ['updated_by'=>2,'lecturer_assignment_id'=>'A','lecturer_plan_revision'=>2]];
        foreach ($invalid as $claims) {
            DB::table('course_days')->where('id',$day->id)->update(['attendance_data'=>json_encode(['participants'=>[33=>array_merge(['present'=>false,'state'=>'dirty'],$claims)]])]);
            $this->assertFalse((new CourseDayAttendanceSyncService($api))->syncToRemote($day->fresh()));
            $this->assertSame('dirty',$day->fresh()->attendance_data['participants'][33]['state']);
        }
    }

    public function test_former_lecturer_cannot_push_an_old_queued_change_on_his_own_former_day(): void
    {
        $day=$this->day('2026-11-04');
        DB::table('course_days')->where('id',$day->id)->update(['attendance_data'=>json_encode(['participants'=>[33=>[
            'present'=>false,'state'=>'dirty','updated_by'=>1,'lecturer_assignment_id'=>'A','lecturer_plan_revision'=>2]]])]);
        $api=\Mockery::mock(ApiUvsService::class);$api->shouldReceive('request')->never();
        $this->assertFalse((new CourseDayAttendanceSyncService($api))->syncToRemote($day->fresh()));
    }

    public function test_historical_pull_uses_system_scope_and_does_not_grant_teacher_write_rights(): void
    {
        $day=$this->day('2026-11-04');$this->actingAs(User::find(2));
        $this->assertFalse(Access::canWriteDay(User::find(2),$day->course,$day));
        $api=\Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('POST','/api/course/courseday/loadattendancedata',\Mockery::on(fn($p)=>$p['date']==='2026-11-04'&&!isset($p['changes'])),[])
            ->andReturn(['ok'=>true,'data'=>['pulled'=>['items'=>[['uid'=>99,'teilnehmer_id'=>'TN-33','fehl_datum_iso'=>'2026-11-04','fehl_std'=>8,'fehl_grund'=>'K','fehl_bem'=>'UVS-Nachweis']]]]]);
        $this->assertTrue(Access::runAsSystem(fn()=>(new CourseDayAttendanceSyncService($api))->loadFromRemote($day)));
        $row=$day->fresh()->attendance_data['participants'][33];
        $this->assertSame('remote',$row['state']);$this->assertSame('UVS-Nachweis',$row['note']);$this->assertSame(99,$row['src_api_id']);
        $this->assertFalse(Access::isSystemSync());$this->assertArrayNotHasKey('updated_by',$row);
    }

    public function test_unchanged_remote_absence_does_not_block_a_different_new_lecturer_edit(): void
    {
        $day=$this->day();
        DB::table('course_days')->where('id',$day->id)->update(['attendance_data'=>json_encode(['participants'=>[44=>['present'=>false,'state'=>'remote','src_api_id'=>55,'note'=>'Unverändert']]])]);
        $day=$day->fresh();$this->actingAs(User::find(2));$day->setAttendance(33,['present'=>false,'state'=>'dirty']);
        $api=\Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('POST','/api/course/courseday/syncattendancedata',\Mockery::on(function($p){
            $this->assertSame(['TN-33'],array_column($p['changes'],'teilnehmer_id'));return true;
        }),[])->andReturn(['ok'=>true]);
        $this->assertTrue((new CourseDayAttendanceSyncService($api))->syncToRemote($day->fresh()));
        $this->assertSame('remote',$day->fresh()->attendance_data['participants'][44]['state']);
    }

    public function test_direct_authenticated_historical_pull_is_blocked_before_remote_request(): void
    {
        $day=$this->day('2026-11-04');$this->actingAs(User::find(2));
        $api=\Mockery::mock(ApiUvsService::class);$api->shouldReceive('request')->never();
        try {
            (new CourseDayAttendanceSyncService($api))->loadFromRemote($day);
            $this->fail('Historical pull outside the explicit system context was accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403,$e->getStatusCode());
        }
        $this->assertFalse(Access::isSystemSync());
        $this->assertSame([], $day->fresh()->attendance_data['participants']);
    }
}
