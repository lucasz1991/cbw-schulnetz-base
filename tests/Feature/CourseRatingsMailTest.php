<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailJob;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\Mail;
use App\Models\Setting;
use App\Notifications\MailNotification;
use App\Services\CourseRatingsPdfExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Tests\TestCase;
use ZipArchive;

class CourseRatingsMailTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
                'session.driver' => 'array', 'activitylog.dispatch_enabled' => false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
            'activitylog.dispatch_enabled' => false, 'app.key' => '01234567890123456789012345678901']);
        DB::purge('sqlite');
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 06:00:00', 'Europe/Berlin'));
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('course_ratings', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->boolean('skip_course_rating')->nullable();
            $t->integer('kb_1')->nullable();
            $t->timestamps();
        });
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->date('date');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
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
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->morphs('fileable');
            $t->string('name');
            $t->string('path');
            $t->string('disk');
            $t->string('type');
            $t->string('mime_type');
            $t->integer('size');
            $t->timestamps();
        });
        Storage::fake('private');
        Notification::fake();
        // The exporter has a real-DomPDF test; here exercise ZIP and mail orchestration.
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('save')->andReturnUsing(function (string $path) use ($pdf) {
            file_put_contents($path, '%PDF-1.4 synthetic course rating');

            return $pdf;
        });
        Pdf::shouldReceive('loadView')->andReturn($pdf);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function enable(): void
    {
        Setting::setValue('mails', 'course_ratings_mail_enabled', true);
        Setting::setValue('mails', 'course_ratings_mail_recipients', 'one@example.test, two@example.test');
        Setting::setValue('mails', 'course_ratings_mail_start_date', '2026-10-01');
    }

    private function course(string $end, ?bool $skip = false, bool $rating = true): Course
    {
        $course = Course::withoutEvents(fn () => Course::create([
            'klassen_id' => 'class-'.uniqid(), 'termin_id' => 'same-term', 'title' => 'Testbaustein',
            'planned_start_date' => $end, 'planned_end_date' => $end,
            'source_snapshot' => ['course' => ['kurzbez' => 'MOD', 'klassen_co_ks' => 'Testklasse']],
        ]));
        if ($rating) {
            CourseRating::withoutEvents(fn () => CourseRating::create(['course_id' => $course->id, 'skip_course_rating' => $skip, 'kb_1' => 4]));
        }

        return $course;
    }

    public function test_disabled_or_invalid_settings_do_not_send(): void
    {
        $this->course('2026-10-04');
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->enable();
        Setting::setValue('mails', 'course_ratings_mail_recipients', 'one@example.test, broken');
        $this->assertSame(1, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(0, Mail::count());
        Notification::assertNothingSent();
    }

    public function test_missing_activation_date_starts_today_without_historical_mail(): void
    {
        $this->enable();
        Setting::where('key', 'course_ratings_mail_start_date')->delete();
        $this->course('2026-10-04');
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame('2026-10-05', Setting::getValueUncached('mails', 'course_ratings_mail_start_date'));
        $this->assertSame(0, Mail::count());
    }

    public function test_weekly_archive_contains_only_previous_fridays_rated_modules_and_sends_once(): void
    {
        $this->enable();
        $first = $this->course('2026-10-02');
        $second = $this->course('2026-10-02', null); // Legacy ratings remain included.
        $this->course('2026-10-01');
        $this->course('2026-09-25');
        $this->course('2026-10-03');
        $this->course('2026-10-04');
        $this->course('2026-10-05');
        $this->course('2026-10-06');
        $this->course('2026-10-02', true);
        $this->course('2026-10-02', false, false);
        $deleted = $this->course('2026-10-02');
        $deleted->deleteQuietly();
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(1, Mail::count());
        Notification::assertCount(2);
        foreach (Mail::all() as $mail) {
            $this->assertTrue((bool) $mail->status);
            $file = $mail->files()->sole();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('private')->path($file->path)));
            $this->assertSame('2026-10-02', $mail->content['course_ratings_date']);
            $expected = [$first->id, $second->id];
            $this->assertSame($expected, $mail->content['course_ids']);
            $this->assertSame(count($expected), $zip->numFiles);
            foreach ($expected as $i => $id) {
                $this->assertStringStartsWith($id.'_same-term_', $zip->getNameIndex($i));
                $this->assertStringStartsWith('%PDF-', $zip->getFromIndex($i));
            }
            $zip->close();
            $message = (new MailNotification($mail))->toMail(new AnonymousNotifiable);
            $this->assertSame('Weiter', $message->actionText);
            $this->assertSame($mail->content['link'], $message->actionUrl);
            $this->assertCount(1, $message->attachments);
            $this->assertSame('application/zip', $message->attachments[0]['options']['mime']);
            $this->assertSame(Storage::disk('private')->path($file->path), $message->attachments[0]['file']);
            // Render the existing mail layout, including plain-text fallback link.
            $html = (string) app(\Illuminate\Mail\Markdown::class)->render($message->markdown, $message->data());
            $this->assertStringContainsString('herunterladen', $html);
            $this->assertStringContainsString('signature=', $html);
        }
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(1, Mail::count());
        Notification::assertCount(2);
    }

    public function test_rejected_attachment_falls_back_to_the_same_download_link(): void
    {
        $this->enable();
        $this->course('2026-10-02');
        $transport = new CourseRatingsTransportFake;
        $transport->rejectAttachments = true;
        Notification::swap($transport);
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame([true, false, true, false], array_column($transport->attempts, 'attachment'));
        $mail = Mail::sole();
        $this->assertTrue((bool) $mail->status);
        foreach ($transport->attempts as $attempt) {
            $this->assertSame($mail->content['link'], $attempt['link']);
        }
        $this->assertCount(1, $mail->files);
    }

    public function test_failed_recipients_retry_without_resending_successful_ones(): void
    {
        $this->enable();
        $this->course('2026-10-02');
        $transport = new CourseRatingsTransportFake;
        $transport->rejectRecipient = 'two@example.test';
        Notification::swap($transport);
        $this->assertSame(1, Artisan::call('course-ratings:dispatch-mail'));
        $mail = Mail::sole();
        $this->assertFalse((bool) $mail->status);
        $this->assertTrue($mail->recipients[0]['status']);
        $this->assertFalse($mail->recipients[1]['status']);
        $transport->rejectRecipient = null;
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(['one@example.test', 'two@example.test', 'two@example.test', 'two@example.test'], array_column($transport->attempts, 'email'));
        $this->assertTrue((bool) $mail->fresh()->status);
        $this->assertSame(1, Mail::count());
    }

    public function test_download_is_private_signed_and_expires_after_thirty_days(): void
    {
        $this->enable();
        $this->course('2026-10-02');
        Artisan::call('course-ratings:dispatch-mail');
        $mail = Mail::sole();
        $link = $mail->content['link'];
        $file = $mail->files()->sole();
        $response = $this->get($link)->assertOk()->assertHeader('content-type', 'application/zip');
        $this->assertSame(Storage::disk('private')->get($file->path), $response->streamedContent());
        $this->get(route('course-ratings.download', $mail->id))->assertForbidden();
        $this->get($link.'&extra=1')->assertForbidden();
        $this->travel(31)->days();
        $this->get($link)->assertForbidden();
        $this->travelBack();
        // Avoid the application's unrelated CMS-backed 404 layout in this isolated schema.
        $this->withoutExceptionHandling();
        try {
            $this->get(URL::temporarySignedRoute('course-ratings.download', now()->addDay(), ['mail' => 999]));
            $this->fail('Missing mail must not be downloadable.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertSame(Mail::class, $e->getModel());
        }
        $mail->update(['content' => ['subject' => 'Ordinary mail']]);
        try {
            $this->get(URL::temporarySignedRoute('course-ratings.download', now()->addDay(), ['mail' => $mail->id]));
            $this->fail('Ordinary mail must not be downloadable.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_actual_array_transport_receives_zip_and_link_in_the_existing_mail_template(): void
    {
        $this->enable();
        $this->course('2026-10-02');
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $sent = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);
        $mail = Mail::sole();
        foreach ($sent as $message) {
            $mime = $message->getOriginalMessage();
            $this->assertCount(1, $mime->getAttachments());
            $this->assertSame('Kursbewertungen_2026-10-02.zip', $mime->getAttachments()[0]->getFilename());
            $this->assertStringContainsString('herunterladen', $mime->getHtmlBody());
            $this->assertStringContainsString($mail->content['link'], $mime->getTextBody());
        }
    }

    public function test_generic_mail_keeps_its_existing_job_behavior_and_default_link_label(): void
    {
        $mail = Mail::withoutEvents(fn () => Mail::create(['type' => 'mail', 'status' => false,
            'content' => ['subject' => 'Ordinary', 'body' => 'Test', 'link' => 'https://example.test'],
            'recipients' => [['email' => 'one@example.test']]]));
        (new ProcessMailJob($mail))->handle();
        $this->assertTrue((bool) $mail->fresh()->status);
        Notification::assertCount(1);
        $this->assertSame('Weiter', (new MailNotification($mail))->toMail(new AnonymousNotifiable)->actionText);
    }

    public function test_existing_scheduler_runs_the_command_in_berlin_at_six(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $event = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'course-ratings:dispatch-mail'));
        $this->assertNotNull($event);
        $this->assertSame('0 6 * * 1', $event->expression);
        $this->assertSame('Europe/Berlin', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_activation_after_friday_does_not_send_historical_modules(): void
    {
        $this->enable();
        Setting::setValue('mails', 'course_ratings_mail_start_date', '2026-10-03');
        $this->course('2026-10-02');
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(0, Mail::count());
        Notification::assertNothingSent();
    }

    public function test_previous_friday_is_correct_at_the_year_boundary_and_berlin_midnight(): void
    {
        $this->enable();
        $this->travelTo(\Carbon\Carbon::parse('2027-01-03 23:30:00', 'UTC'));
        $this->course('2027-01-01');
        $this->course('2026-12-25');
        $this->assertSame(0, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame('2027-01-01', Mail::sole()->content['course_ratings_date']);
    }

    public function test_failed_pdf_export_does_not_persist_or_send_partial_archives(): void
    {
        $this->enable();
        $this->course('2026-10-02');
        $exporter = \Mockery::mock(CourseRatingsPdfExporter::class);
        $exporter->shouldReceive('generate')->andThrow(new \RuntimeException('Synthetic export failure'));
        app()->instance(CourseRatingsPdfExporter::class, $exporter);
        $this->assertSame(1, Artisan::call('course-ratings:dispatch-mail'));
        $this->assertSame(0, Mail::count());
        $this->assertSame([], Storage::disk('private')->allFiles('course-ratings'));
        Notification::assertNothingSent();
    }
}

class CourseRatingsTransportFake extends NotificationFake
{
    public bool $rejectAttachments = false;

    public ?string $rejectRecipient = null;

    public array $attempts = [];

    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        $message = $notification->toMail($notifiables);
        $email = $notifiables->routeNotificationFor('mail');
        $attached = count($message->attachments) > 0;
        $this->attempts[] = ['email' => $email, 'attachment' => $attached, 'link' => $message->actionUrl];
        if (($this->rejectAttachments && $attached) || $email === $this->rejectRecipient) {
            throw new \RuntimeException('Synthetic mail transport rejection');
        }
        parent::sendNow($notifiables, $notification, $channels);
    }
}
