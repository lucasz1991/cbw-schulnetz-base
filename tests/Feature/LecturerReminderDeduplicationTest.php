<?php

namespace Tests\Feature;

use App\Console\Commands\DispatchLecturerReminders;
use App\Jobs\ProcessMailJob;
use App\Models\Course;
use App\Models\Mail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LecturerReminderDeduplicationTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
                'session.driver' => 'array', 'activitylog.dispatch_enabled' => false,
            ]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        Bus::fake([ProcessMailJob::class]);
        $this->travelTo(Carbon::parse('2026-10-30 09:30:00', 'Europe/Berlin'));
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id')->nullable();
            $t->string('email_priv')->nullable();
            $t->string('email_cbw')->nullable();
            $t->softDeletes();
        });
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->date('date');
            $t->integer('note_status')->nullable();
            $t->softDeletes();
        });
        Schema::create('mails', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->boolean('status')->default(false);
            $t->json('content');
            $t->json('recipients');
            $t->timestamps();
        });
        DB::table('persons')->insert(['id' => 1, 'email_cbw' => 'tutor@example.test']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_documentation_command_sends_once_per_friday_and_preserves_the_second_weekly_reminder(): void
    {
        DB::table('settings')->insert([
            'type' => 'mails', 'key' => 'lecturer_module_documentation_missing', 'value' => 'true',
        ]);
        $course = $this->course();
        DB::table('course_days')->insert(['course_id' => $course->id, 'date' => '2026-10-29', 'note_status' => 0]);

        $this->assertSame(0, Artisan::call('courses:dispatch-lecturer-reminders'));
        $this->assertSame(0, Artisan::call('courses:dispatch-lecturer-reminders'));
        $this->assertSame(1, Mail::count());
        $this->assertSame(1, $course->fresh()->getSetting('reminder_state_lecturer_module_documentation_missing')['count']);

        $this->travelTo(Carbon::parse('2026-11-06 09:30:00', 'Europe/Berlin'));
        Artisan::call('courses:dispatch-lecturer-reminders');
        Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(2, Mail::count());
        $this->assertSame(2, $course->fresh()->getSetting('reminder_state_lecturer_module_documentation_missing')['count']);

        $this->travelTo(Carbon::parse('2026-11-13 09:30:00', 'Europe/Berlin'));
        Artisan::call('courses:dispatch-lecturer-reminders');
        $this->assertSame(2, Mail::count());
    }

    public function test_stale_course_instances_recheck_persisted_state_instead_of_creating_a_second_mail(): void
    {
        $course = $this->course();
        $stale = $course->fresh();
        $this->assertSame(1, $this->send($course));
        $this->assertSame(0, $this->send($stale));
        $this->assertSame(1, Mail::count());
        $this->assertSame(1, $course->fresh()->getSetting('reminder_state_documentation')['count']);
    }

    public function test_other_reminder_types_and_courses_are_not_suppressed_or_overwritten(): void
    {
        $course = $this->course(['preserved' => 'existing']);
        $stale = $course->fresh();
        $other = $this->course();
        $this->assertSame(1, $this->send($course));
        $this->assertSame(1, $this->send($stale, 'attendance'));
        $this->assertSame(1, $this->send($other));
        $this->assertSame(3, Mail::count());
        $settings = $course->fresh()->settings;
        $this->assertSame('existing', $settings['preserved']);
        $this->assertSame(1, $settings['reminder_state_documentation']['count']);
        $this->assertSame(1, $settings['reminder_state_attendance']['count']);
    }

    public function test_attendance_keeps_its_two_day_interval(): void
    {
        $course = $this->course();
        $this->assertSame(1, $this->send($course, 'attendance', everyDays: 2));
        $this->travelTo(Carbon::parse('2026-10-31 09:30:00', 'Europe/Berlin'));
        $this->assertSame(0, $this->send($course, 'attendance', everyDays: 2));
        $this->travelTo(Carbon::parse('2026-11-01 09:30:00', 'Europe/Berlin'));
        $this->assertSame(1, $this->send($course, 'attendance', everyDays: 2));
        $this->assertSame(2, Mail::count());
    }

    public function test_once_only_types_still_send_once_across_different_days(): void
    {
        $course = $this->course();
        $this->assertSame(1, $this->send($course, 'roter_faden', once: true));
        $this->travelTo(Carbon::parse('2026-11-06 09:30:00', 'Europe/Berlin'));
        $this->assertSame(0, $this->send($course, 'roter_faden', once: true));
        $this->assertSame(1, Mail::count());
    }

    public function test_same_day_guard_compares_utc_timestamps_in_berlin(): void
    {
        $course = $this->course(['reminder_state_documentation' => [
            'count' => 1, 'last_sent_at' => '2026-10-29T23:30:00+00:00',
        ]]);
        $this->assertSame(0, $this->send($course));
        $this->assertSame(0, Mail::count());
    }

    public function test_failed_state_save_rolls_back_the_mail_record(): void
    {
        $course = $this->course();
        Event::listen('eloquent.saving: '.Course::class, function () {
            throw new \RuntimeException('synthetic state write failure');
        });
        try {
            $this->send($course);
            $this->fail('The state write should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('synthetic state write failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.saving: '.Course::class);
        }

        $this->assertSame(0, Mail::count());
        $this->assertNull($course->fresh()->getSetting('reminder_state_documentation'));
        $this->assertSame(1, $this->send($course));
        $this->assertSame(1, Mail::count());
    }

    public function test_dry_run_does_not_consume_a_reminder_and_deleted_courses_are_skipped(): void
    {
        $course = $this->course();
        $this->assertSame(1, $this->send($course, dryRun: true));
        $this->assertSame(0, Mail::count());
        $this->assertNull($course->fresh()->getSetting('reminder_state_documentation'));
        $this->assertSame(1, $this->send($course));
        DB::table('courses')->where('id', $course->id)->update(['deleted_at' => now()]);
        $this->assertSame(0, $this->send($course, 'attendance'));
        $this->assertSame(1, Mail::count());
    }

    private function course(array $settings = []): Course
    {
        return Course::withoutEvents(fn () => Course::create([
            'klassen_id' => 'class-'.uniqid(), 'title' => 'Reminder test',
            'planned_start_date' => '2026-10-26', 'planned_end_date' => '2026-11-30',
            'primary_tutor_person_id' => 1, 'settings' => $settings,
        ]));
    }

    private function send(Course $course, string $type = 'documentation', ?int $everyDays = null, bool $once = false, bool $dryRun = false): int
    {
        $command = app(DispatchLecturerReminders::class);
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        ));

        return (new \ReflectionMethod($command, 'sendReminder'))->invoke(
            $command, $course, $type, 'Reminder '.$type, ['Synthetic reminder'],
            Carbon::today('Europe/Berlin'), $dryRun, $everyDays, 2, $once
        );
    }
}
