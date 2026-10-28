<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailJob;
use App\Models\Mail;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail as Mailer;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReminderDeliveryIntegrationTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
                'session.driver' => 'array', 'activitylog.dispatch_enabled' => false,
                'mail.from.address' => 'sender@example.test', 'mail.from.name' => 'Test',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
        Schema::create('mails', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->boolean('status');
            $table->json('content');
            $table->json('recipients');
            $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('message');
            $table->integer('from_user');
            $table->integer('to_user');
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->morphs('fileable');
        });
        User::forceCreate(['id' => 1, 'name' => 'Tutor', 'email' => 'tutor@example.test']);
    }

    public function test_one_real_mail_and_one_internal_message_for_mixed_duplicate_recipients_and_replay(): void
    {
        $mail = Mail::withoutEvents(fn () => Mail::create($this->payload([
            ['user_id' => 1, 'email' => null, 'status' => false],
            ['user_id' => null, 'email' => 'tutor@example.test', 'status' => false],
            ['user_id' => null, 'email' => 'TUTOR@EXAMPLE.TEST', 'status' => false],
        ])));
        $job = new ProcessMailJob($mail);
        $staleReplay = new ProcessMailJob($mail->fresh());
        $job->handle();

        $transport = Mailer::mailer('array')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $this->assertSame('tutor@example.test', $transport->messages()->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertTrue((bool) $mail->fresh()->status);

        $staleReplay->handle();
        $job->handle();

        $this->assertCount(1, $transport->messages());
        $this->assertSame(1, DB::table('messages')->count());
    }

    public function test_legacy_completed_user_recipient_also_prevents_pending_direct_duplicate(): void
    {
        $mail = Mail::withoutEvents(fn () => Mail::create($this->payload([
            ['user_id' => 1, 'email' => null, 'status' => true],
            ['user_id' => null, 'email' => 'TUTOR@EXAMPLE.TEST', 'status' => false],
        ])));

        (new ProcessMailJob($mail))->handle();

        $this->assertCount(0, Mailer::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(0, DB::table('messages')->count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_stale_job_replay_does_not_send_a_second_real_mail(): void
    {
        $mail = Mail::withoutEvents(fn () => Mail::create($this->payload([
            ['user_id' => 1, 'email' => null, 'status' => false],
        ])));
        $first = new ProcessMailJob($mail);
        $replay = new ProcessMailJob($mail->fresh());
        $first->handle();
        $this->assertCount(1, Mailer::mailer('array')->getSymfonyTransport()->messages());

        $replay->handle();

        $this->assertCount(1, Mailer::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(1, DB::table('messages')->count());
    }

    public function test_different_real_addresses_remain_separate_deliveries(): void
    {
        $mail = Mail::withoutEvents(fn () => Mail::create($this->payload([
            ['user_id' => 1, 'email' => null, 'status' => false],
            ['user_id' => null, 'email' => 'second@example.test', 'status' => false],
        ])));

        (new ProcessMailJob($mail))->handle();

        $transport = Mailer::mailer('array')->getSymfonyTransport();
        $this->assertCount(2, $transport->messages());
        $this->assertSame(['tutor@example.test', 'second@example.test'], $transport->messages()
            ->map(fn ($message) => $message->getOriginalMessage()->getTo()[0]->getAddress())->all());
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_sync_delivery_waits_for_commit_and_rollback_does_not_send(): void
    {
        $transport = Mailer::mailer('array')->getSymfonyTransport();
        $payload = $this->payload([['user_id' => 1, 'email' => null, 'status' => false]]);

        DB::beginTransaction();
        Mail::create($payload);
        $this->assertCount(0, $transport->messages());
        $this->assertSame(0, DB::table('messages')->count());
        DB::rollBack();
        $this->assertCount(0, $transport->messages());
        $this->assertSame(0, Mail::count());

        DB::beginTransaction();
        $mail = Mail::create($payload);
        $this->assertCount(0, $transport->messages());
        DB::commit();

        $this->assertCount(1, $transport->messages());
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    private function payload(array $recipients): array
    {
        return ['type' => 'both', 'status' => false,
            'content' => ['subject' => 'Erinnerung: Test', 'body' => 'Bitte vervollstaendigen.', 'header' => 'Hallo,'],
            'recipients' => $recipients];
    }
}
