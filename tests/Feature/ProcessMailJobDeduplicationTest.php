<?php

namespace Tests\Feature;

use App\Jobs\ProcessMailJob;
use App\Models\Mail;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use RuntimeException;
use Tests\TestCase;

class ProcessMailJobDeduplicationTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
                'activitylog.dispatch_enabled' => false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('mails', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->boolean('status')->default(false);
            $table->json('content');
            $table->json('recipients');
            $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('message');
            $table->unsignedInteger('from_user');
            $table->unsignedInteger('to_user');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->morphs('fileable');
            $table->timestamps();
        });
        Notification::fake();
    }

    public function test_legacy_user_and_email_rows_deliver_only_once_to_each_mailbox(): void
    {
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([
            ['user_id' => $user->id, 'email' => null, 'status' => false],
            ['user_id' => null, 'email' => 'tutor@example.test', 'status' => false],
            ['user_id' => null, 'email' => ' TUTOR@example.test ', 'status' => false],
            ['user_id' => null, 'email' => 'other@example.test', 'status' => false],
        ]);

        (new ProcessMailJob($mail))->handle();

        Notification::assertCount(2);
        $this->assertSame(1, Message::count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_replayed_stale_job_keeps_successful_channels_and_rows(): void
    {
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([['user_id' => $user->id, 'status' => false]]);
        $staleJob = new ProcessMailJob($mail->replicate()->setRawAttributes($mail->getAttributes(), true));

        (new ProcessMailJob($mail))->handle();
        $staleJob->handle();

        Notification::assertCount(1);
        $this->assertSame(1, Message::count());
    }

    public function test_existing_successful_email_row_suppresses_user_mail_but_preserves_internal_message(): void
    {
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([
            ['user_id' => $user->id, 'status' => false],
            ['email' => 'TUTOR@example.test', 'status' => true],
        ]);

        (new ProcessMailJob($mail))->handle();

        Notification::assertNothingSent();
        $this->assertSame(1, Message::count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_separate_users_sharing_email_each_receive_an_internal_message(): void
    {
        $first = User::create(['name' => 'First synthetic tutor', 'email' => 'shared@example.test']);
        $second = User::create(['name' => 'Second synthetic tutor', 'email' => 'SHARED@example.test']);
        $mail = $this->mail([
            ['user_id' => $first->id], ['user_id' => $first->id], ['user_id' => $second->id],
        ]);

        (new ProcessMailJob($mail))->handle();

        Notification::assertCount(1);
        $this->assertSame([$first->id, $second->id], Message::orderBy('id')->pluck('to_user')->all());
    }

    public function test_mail_and_message_types_preserve_their_respective_channels(): void
    {
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        (new ProcessMailJob($this->mail([['user_id' => $user->id]], 'message')))->handle();
        (new ProcessMailJob($this->mail([['user_id' => $user->id]], 'mail')))->handle();

        Notification::assertCount(1);
        $this->assertSame(1, Message::count());
    }

    public function test_created_mail_waits_for_outer_commit_even_with_the_sync_queue(): void
    {
        DB::beginTransaction();
        $mail = Mail::create(['type' => 'mail', 'content' => ['subject' => 'Synthetic reminder', 'body' => 'Synthetic'],
            'recipients' => [['email' => 'one@example.test', 'status' => false]]]);
        Notification::assertNothingSent();
        $this->assertFalse((bool) $mail->fresh()->status);
        DB::commit();

        Notification::assertCount(1);
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_rolled_back_mail_never_dispatches_the_sync_worker(): void
    {
        DB::beginTransaction();
        Mail::create(['type' => 'mail', 'content' => ['subject' => 'Synthetic reminder', 'body' => 'Synthetic'],
            'recipients' => [['email' => 'one@example.test', 'status' => false]]]);
        DB::rollBack();

        Notification::assertNothingSent();
        $this->assertSame(0, Mail::count());
    }

    public function test_failed_mail_channel_retries_without_repeating_internal_or_other_successful_delivery(): void
    {
        $notifications = new FailingOnceReminderNotificationFake;
        Notification::swap($notifications);
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([
            ['user_id' => $user->id, 'status' => false],
            ['email' => 'other@example.test', 'status' => false],
        ]);

        (new ProcessMailJob($mail))->handle();

        $recipients = $mail->fresh()->recipients;
        $this->assertFalse((bool) $mail->fresh()->status);
        $this->assertTrue($recipients[0]['message_status']);
        $this->assertFalse($recipients[0]['mail_status']);
        $this->assertTrue($recipients[1]['status']);
        $this->assertSame(1, Message::count());
        Notification::assertCount(1);

        (new ProcessMailJob($mail))->handle();

        Notification::assertCount(2);
        $this->assertSame(1, Message::count());
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_failed_message_channel_preserves_successful_email_for_retry(): void
    {
        $fail = true;
        Message::creating(function () use (&$fail): void {
            if ($fail) {
                $fail = false;
                throw new RuntimeException('Synthetic message write failure');
            }
        });
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([['user_id' => $user->id, 'status' => false]]);

        (new ProcessMailJob($mail))->handle();

        $this->assertSame(0, Message::count());
        Notification::assertCount(1);
        $this->assertFalse((bool) $mail->fresh()->status);
        $this->assertTrue($mail->fresh()->recipients[0]['mail_status']);

        (new ProcessMailJob($mail))->handle();

        $this->assertSame(1, Message::count());
        Notification::assertCount(1);
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_interrupted_later_recipient_cannot_roll_back_an_earlier_success(): void
    {
        $mail = $this->mail([['email' => 'first@example.test'], ['email' => 'second@example.test']], 'mail');
        $reads = 0;
        $fail = true;
        DB::listen(function ($query) use (&$reads, &$fail): void {
            if (str_starts_with($query->sql, 'select * from "mails"')) {
                $reads++;
                if ($fail && $reads === 3) {
                    $fail = false;
                    throw new RuntimeException('Synthetic worker interruption before second recipient');
                }
            }
        });

        try {
            (new ProcessMailJob($mail))->handle();
            $this->fail('The worker must be interrupted.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic worker interruption before second recipient', $error->getMessage());
        }
        $this->assertTrue($mail->fresh()->recipients[0]['status']);
        $this->assertFalse((bool) $mail->fresh()->status);
        Notification::assertCount(1);

        (new ProcessMailJob($mail))->handle();

        Notification::assertCount(2);
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_invalid_recipient_does_not_block_valid_remaining_rows(): void
    {
        $mail = $this->mail([['user_id' => 999], ['email' => 'invalid'], ['email' => 'valid@example.test']], 'mail');

        (new ProcessMailJob($mail))->handle();
        (new ProcessMailJob($mail))->handle();

        Notification::assertCount(1);
        $this->assertFalse((bool) $mail->fresh()->status);
        $this->assertTrue($mail->fresh()->recipients[2]['status']);
    }

    public function test_partial_success_keeps_actual_delivery_address_when_account_email_changes(): void
    {
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'new@example.test']);
        $mail = $this->mail([
            ['user_id' => $user->id, 'message_status' => false, 'mail_status' => true, 'mail_address' => 'old@example.test'],
            ['email' => 'new@example.test', 'status' => false],
        ]);

        (new ProcessMailJob($mail))->handle();

        $this->assertSame('old@example.test', $mail->fresh()->recipients[0]['mail_address']);
        $this->assertSame(1, Message::count());
        Notification::assertCount(1);
        $this->assertTrue((bool) $mail->fresh()->status);
    }

    public function test_database_deadlock_during_internal_delivery_aborts_before_sending_mail(): void
    {
        Message::creating(fn () => throw new \PDOException('deadlock detected'));
        $user = User::create(['name' => 'Synthetic Tutor', 'email' => 'tutor@example.test']);
        $mail = $this->mail([['user_id' => $user->id, 'status' => false]]);

        try {
            (new ProcessMailJob($mail))->handle();
            $this->fail('Database deadlocks must leave the worker for a later retry.');
        } catch (\PDOException $error) {
            $this->assertStringContainsString('deadlock detected', $error->getMessage());
        }

        Notification::assertNothingSent();
        $this->assertSame(0, Message::count());
        $this->assertFalse((bool) $mail->fresh()->status);
    }

    public function test_database_error_after_sending_mail_does_not_automatically_repeat_smtp_in_same_run(): void
    {
        $fail = true;
        Mail::updating(function () use (&$fail): void {
            if ($fail) {
                $fail = false;
                throw new \PDOException('deadlock detected');
            }
        });
        $mail = $this->mail([['email' => 'tutor@example.test', 'status' => false]], 'mail');

        try {
            (new ProcessMailJob($mail))->handle();
            $this->fail('A failed commit must not automatically repeat an external send.');
        } catch (\PDOException $error) {
            $this->assertStringContainsString('deadlock detected', $error->getMessage());
        }

        Notification::assertCount(1);
        $this->assertFalse((bool) $mail->fresh()->status);
    }

    private function mail(array $recipients, string $type = 'both'): Mail
    {
        return Mail::withoutEvents(fn () => Mail::create(['type' => $type, 'status' => false,
            'content' => ['subject' => 'Synthetic reminder', 'body' => 'Synthetic reminder body'], 'recipients' => $recipients]));
    }
}

class FailingOnceReminderNotificationFake extends NotificationFake
{
    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        static $failed = false;
        if (! $failed) {
            $failed = true;
            throw new RuntimeException('Synthetic SMTP failure');
        }

        return parent::sendNow($notifiables, $notification, $channels);
    }
}
