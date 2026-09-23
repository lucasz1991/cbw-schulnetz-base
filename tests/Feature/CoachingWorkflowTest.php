<?php

namespace Tests\Feature;

use App\Models\{CoachingContract, CoachingOutbox, Course, CourseDay, Person, User};
use App\Services\Coaching\{CourseProjector, PlanService, PlanValidator, SyncService};
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Queue, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CoachingWorkflowTest extends TestCase
{
    /** Override database config before providers boot: never use the imported/local project database. */
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
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake();
        \Illuminate\Support\Facades\Notification::fake();
        \Carbon\Carbon::setTestNow('2026-09-17 08:00:00');
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->default('guest'); $t->timestamps(); });
        Schema::create('persons', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('person_id'); $t->integer('institut_id');
            $t->string('role')->default('guest'); $t->json('statusdata')->nullable(); $t->json('programdata')->nullable();
            $t->timestamp('last_api_update')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->text('message'); $t->integer('from_user'); $t->integer('to_user'); $t->integer('status'); $t->timestamps(); });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        \App\Models\Setting::setValue('coaching', 'enabled', true);
        \App\Models\Setting::setValue('api', 'base_api_url', 'https://schulnetz.example.test');
        foreach (['2025_09_10_152938_create_courses_table.php', '2025_09_10_152939_create_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php', '2026_09_17_080000_create_coaching_planning_tables.php', '2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php', '2026_09_22_100000_create_coaching_notices.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('course_days', function (Blueprint $t) { $t->integer('note_status')->default(0); $t->json('settings')->nullable(); });
    }

    protected function tearDown(): void { \Carbon\Carbon::setTestNow(); parent::tearDown(); }

    public function test_draft_plan_is_transferred_then_released_only_after_uvs_employee_activation(): void
    {
        [$participant, $tutor] = $this->people();
        $sync = app(SyncService::class);
        $sync->importContract($this->importRow(['status' => 'draft']));
        $contract = CoachingContract::firstOrFail();
        $this->assertTrue($contract->planningAllowed());
        $service = app(PlanService::class);
        $plan = $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $tutor, 1);
        $service->confirm($contract->id, $participant, 1);
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->withArgs(fn ($method, $url, $payload) => $method === 'PUT' && $url === '/api/coaching/contracts/1/plan' && $payload['revision'] === 1)
            ->andReturn(['ok' => true, 'data' => ['revision' => 1]]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(1, $sync->sendPending());
        $this->assertDatabaseCount('courses', 0);
        $this->assertSame(2, \App\Models\CoachingNotice::where('kind', 'plan_transferred')->count());
        $this->assertSame(0, \App\Models\CoachingNotice::where('kind', 'released')->count());
        $row = $this->importRow(['status' => 'active', 'version' => str_repeat('b',64), 'teilnehmer_id' => '1-final',
            'plan_revision' => 1, 'plan_hash' => hash('sha256', json_encode(['1-200', $plan->fresh()->items], JSON_UNESCAPED_UNICODE))]);
        $sync->importContract($row); $sync->importContract($row);
        $this->assertDatabaseCount('courses', 1);
        $this->assertTrue($contract->fresh()->startReady());
        $this->assertSame(str_repeat('b',64), $plan->fresh()->contract_version);
        $this->assertSame(2, \App\Models\CoachingNotice::where('kind', 'released')->count());
        $this->assertStringContainsString('Erster Termin: 21.09.2026', implode(' ', \App\Models\CoachingNotice::where('kind', 'released')->first()->content['lines']));
    }

    public function test_failed_uvs_transfer_never_announces_success_or_releases_a_course(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t); $contract->update(['contract_status' => 'draft']);
        $service = app(PlanService::class);
        $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $participant, 1); $service->confirm($contract->id, $tutor, 1);
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->andReturn(['ok' => false, 'status' => 503]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->sendPending());
        $this->assertSame(0, \App\Models\CoachingNotice::whereIn('kind', ['plan_transferred', 'released'])->count());
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_unregistered_recipients_get_invites_and_inbox_is_added_after_exact_identity_registration(): void
    {
        User::forceCreate(['id' => 1, 'name' => 'Schulnetz System', 'role' => 'admin']);
        $row = $this->importRow(['status' => 'draft', 'contacts' => [
            'participant' => ['person_id' => '1-100', 'email' => 'participant@example.test'],
            'tutor' => ['person_id' => '1-200', 'email' => 'tutor@example.test'],
        ]]);
        $sync = app(SyncService::class); $notices = app(\App\Services\Coaching\NoticeService::class);
        $sync->importContract($row); $notices->deliverPending();
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\CoachingNotification::class,
            fn ($n, $channels, $recipient) => $n->registration && $n->url === 'https://schulnetz.example.test/register' && $recipient->routes['mail'] === 'participant@example.test');
        \Illuminate\Support\Facades\Notification::assertCount(2);
        $this->assertDatabaseCount('messages', 0);
        $this->assertSame(2, \App\Models\CoachingNotice::whereNotNull('mail_sent_at')->whereNull('message_id')->count());
        $user = User::create(['name' => 'Test Participant', 'email' => 'participant@example.test', 'role' => 'guest']);
        $foreign = Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => '2-100', 'institut_id' => 2, 'role' => 'guest']));
        $notices->linkRegisteredUser($user->fresh());
        $this->assertNull(CoachingContract::first()->participant_person_id);
        $person = Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => '1-100', 'institut_id' => 1, 'role' => 'guest']));
        $notices->linkRegisteredUser($user->fresh()); $notices->deliverPending(); $notices->deliverPending();
        $this->assertSame($person->id, CoachingContract::first()->participant_person_id);
        $this->assertSame(1, \App\Models\Message::where('to_user', $user->id)->count());
        \Illuminate\Support\Facades\Notification::assertCount(2); // Do not resend the already delivered email.
    }

    public function test_mail_retry_preserves_exactly_one_inbox_message(): void
    {
        [, $tutor] = $this->people(); $tutor->update(['email' => 'tutor@example.test']);
        app(SyncService::class)->importContract($this->importRow());
        \Illuminate\Support\Facades\Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function send($notifiables, $notification) { throw new \RuntimeException('Transport down'); }
        });
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $notice = \App\Models\CoachingNotice::where('recipient_role', 'tutor')->firstOrFail();
        $this->assertNotNull($notice->message_id); $this->assertNull($notice->mail_sent_at);
        $this->assertStringContainsString('E-Mail-Versand fehlgeschlagen', $notice->last_error);
        \Illuminate\Support\Facades\Notification::fake();
        $notice->update(['available_at' => now()]);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertSame(1, \App\Models\Message::where('to_user', $tutor->id)->count());
        $this->assertNotNull($notice->fresh()->mail_sent_at);
        \Illuminate\Support\Facades\Notification::assertCount(1);
    }

    public function test_shared_email_cannot_merge_unregistered_participant_and_tutor(): void
    {
        $row = $this->importRow(['contacts' => [
            'participant' => ['person_id' => '1-100', 'email' => 'same@example.test'],
            'tutor' => ['person_id' => '1-200', 'email' => 'same@example.test'],
        ]]);
        app(SyncService::class)->importContract($row);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertSame(2, \App\Models\CoachingNotice::where('last_error', 'like', '%unterschiedliche%')->count());
    }

    public function test_real_registration_retains_draft_planning_access_and_links_existing_notice(): void
    {
        Schema::table('users', function (Blueprint $t) { $t->string('password')->nullable(); $t->integer('status')->nullable(); $t->integer('current_team_id')->nullable(); });
        $payload = (object)['person_id' => '1-100', 'institut_id' => 1, 'vorname' => 'Anna', 'nachname' => 'Test', 'email_priv' => 'anna@example.test'];
        foreach (array_keys(Person::mapFromUvsPayload($payload, 'guest')) as $column) {
            if (!Schema::hasColumn('persons', $column)) Schema::table('persons', fn (Blueprint $t) => $t->text($column)->nullable());
        }
        app(SyncService::class)->importContract($this->importRow(['status' => 'draft']));
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getParticipantbyMail')->with('anna@example.test')->once()->andReturn(['ok' => true, 'data' => ['person' => (array)$payload]]);
        $api->shouldReceive('getPersonStatus')->with('1-100')->once()->andReturn(['data' => ['data' => ['coaching_contracts' => [['status' => 'draft']]]]]);
        $this->app->instance(ApiUvsService::class, $api);
        $registration = new class extends \App\Livewire\Auth\Register {
            protected function generateResetToken($user) { return 'isolated-test-token'; }
        };
        $registration->email = 'anna@example.test';
        $registration->register();
        $user = User::where('email', 'anna@example.test')->firstOrFail();
        $person = $user->persons()->firstOrFail();
        $this->assertTrue($person->hasPortalIdentity());
        $this->assertTrue($person->hasValidParticipantContract());
        $this->assertSame($person->id, CoachingContract::first()->participant_person_id);
        \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\SetPasswordNotification::class);
        $login = new class extends \App\Livewire\Auth\Login {
            public function checkWindow(User $user): void { $this->ensureParticipantLoginWindow($user); }
        };
        $login->checkWindow($user); // A draft coaching needs planning access before any ordinary course exists.
        $this->assertDatabaseCount('courses', 0);
        $tutorPayload = (object)['person_id' => '1-200', 'institut_id' => 1, 'vorname' => 'Tom', 'nachname' => 'Test', 'email_priv' => 'tom@example.test'];
        $api->shouldReceive('getParticipantbyMail')->with('tom@example.test')->once()->andReturn(['ok' => true, 'data' => ['person' => (array)$tutorPayload]]);
        $api->shouldReceive('getPersonStatus')->with('1-200')->once()->andReturn(['data' => ['data' => ['is_tutor' => true, 'mitarbeiter_vertrag_ky' => 'IS', 'mitarbeiter_id' => '1-tutor']]]);
        $registration->email = 'tom@example.test'; $registration->register();
        $tutor = User::where('email', 'tom@example.test')->firstOrFail();
        $this->assertSame('tutor', $tutor->role);
        $this->assertSame($tutor->persons()->first()->id, CoachingContract::first()->tutor_person_id);
        \Illuminate\Support\Facades\Notification::assertSentTo($tutor, \App\Notifications\SetPasswordNotification::class);
    }

    public function test_changed_scope_cannot_release_a_previously_confirmed_draft(): void
    {
        [$participant, $tutor] = $this->people(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow(['status' => 'draft']));
        $contract = CoachingContract::first(); $service = app(PlanService::class);
        $plan = $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        $sync->importContract($this->importRow(['status' => 'active', 'version' => str_repeat('b',64),
            'planning_fingerprint' => str_repeat('c',64), 'agreed_minutes' => 360, 'plan_revision' => 1,
            'plan_hash' => hash('sha256', json_encode(['1-200', $plan->fresh()->items], JSON_UNESCAPED_UNICODE))]));
        $this->assertDatabaseCount('courses', 0);
        $this->assertFalse($contract->fresh()->startReady());
        $this->assertSame(0, \App\Models\CoachingNotice::where('kind', 'released')->count());
    }

    public function test_system_events_cover_chat_changes_cancellation_and_resume_once(): void
    {
        [$participant, $tutor] = $this->people(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow()); $contract = CoachingContract::first();
        $plans = app(PlanService::class);
        $plans->propose($contract->id, $tutor, 0, $this->items());
        $plans->message($contract->id, $participant, 'Passt Dienstag?');
        $sync->importContract($this->importRow(['version' => str_repeat('b',64), 'agreed_minutes' => 90]));
        $sync->importContract($this->importRow(['version' => str_repeat('c',64), 'status' => 'inactive']));
        $sync->importContract($this->importRow(['version' => str_repeat('c',64), 'status' => 'inactive']));
        $sync->importContract($this->importRow(['version' => str_repeat('d',64)]));
        foreach (['plan_changed', 'stopped', 'resumed'] as $kind) $this->assertSame(2, \App\Models\CoachingNotice::where('kind', $kind)->count(), $kind);
        $this->assertSame(1, \App\Models\CoachingNotice::where('kind', 'chat_message')->count());
        $this->assertSame(1, \App\Models\CoachingNotice::where('kind', 'plan_proposed')->count());
    }

    public function test_coaching_mail_uses_existing_schulnetz_template_and_escapes_contract_text(): void
    {
        [, , $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $contract->update(['title' => '<script>alert(1)</script>']);
        $content = app(\App\Services\Coaching\NoticeService::class)->content($contract, 'planning_requested', 'participant');
        $mail = (new \App\Notifications\CoachingNotification($content, 'https://schulnetz.example.test/register', true))->toMail(new \stdClass());
        $this->assertSame('notifications::email', $mail->markdown);
        $html = (string)app(\Illuminate\Mail\Markdown::class)->render($mail->markdown, $mail->data());
        $this->assertStringContainsString('CBW', $html);
        $this->assertStringContainsString('Im Schulnetz registrieren', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_shared_setting_changes_apply_to_open_planning_and_sync_without_cache_refresh(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::withQueryParams(['contract' => $contract->id])
            ->test(\App\Livewire\Coaching\Planning::class)->assertSet('contractId', $contract->id);
        config(['coaching.enabled' => true]);
        \Illuminate\Support\Facades\Cache::put('settings.coaching.enabled', true, 3600);
        // Simulate an Admin write through the shared database, without invalidating Base cache.
        DB::table('settings')->where('type', 'coaching')->where('key', 'enabled')->update(['value' => 'false']);
        $this->assertFalse(\App\Services\Coaching\Access::available());
        // The site's custom 404 layout needs CMS tables; assert the real HTTP denial directly.
        try {
            $component->instance()->edit();
            $this->fail('Eine bereits geöffnete Planung darf nach Abschaltung nicht bearbeitet werden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->artisan('coaching:sync')->expectsOutput('Einzelcoaching-Abgleich ist ausgeschaltet.')->assertSuccessful();
        $this->assertSame(0, app(SyncService::class)->import());
        $this->assertSame(0, app(SyncService::class)->sendPending());
        $this->assertDatabaseCount('coaching_contracts', 1);
        DB::table('settings')->where('type', 'coaching')->where('key', 'enabled')->update(['value' => 'true']);
        $this->assertTrue(\App\Services\Coaching\Access::available());
        \App\Models\Setting::where('type', 'coaching')->delete();
        $this->assertFalse(\App\Services\Coaching\Access::available());
    }

    private function people(): array
    {
        return Person::withoutEvents(function () {
            User::firstOrCreate(['id' => 1], ['name' => 'Schulnetz System', 'role' => 'admin']);
            $participant = User::create(['name' => 'Test Teilnehmer', 'role' => 'guest']);
            $tutor = User::create(['name' => 'Test Dozent', 'role' => 'tutor']);
            $p = Person::create(['user_id' => $participant->id, 'person_id' => '1-100', 'institut_id' => 1, 'role' => 'guest']);
            $t = Person::create(['user_id' => $tutor->id, 'person_id' => '1-200', 'institut_id' => 1, 'role' => 'tutor']);
            return [$participant, $tutor, $p, $t];
        });
    }

    private function contract(Person $p, Person $t, int $uvsId = 1): CoachingContract
    {
        return CoachingContract::create(['uuid' => (string)Str::uuid(), 'uvs_contract_id' => $uvsId, 'institut_id' => 1,
            'uvs_person_id' => $p->person_id, 'beratung_id' => 'test-'.$uvsId, 'participant_person_id' => $p->id,
            'tutor_person_id' => $t->id, 'uvs_tutor_person_id' => $t->person_id, 'title' => 'Test Einzelcoaching', 'agreed_minutes' => 180, 'unit_minutes' => 45,
            'contract_version' => str_repeat('a', 64), 'last_imported_at' => now(), 'teilnehmer_id' => '1-test']);
    }

    private function items(): array
    {
        return array_map(fn ($date) => ['id' => (string)Str::uuid(), 'date' => $date, 'start' => '09:00', 'end' => '10:30',
            'topic' => 'Bewerbung', 'format' => 'online', 'location' => 'Online'], ['2026-09-21', '2026-09-28']);
    }

    private function importRow(array $overrides = []): array
    {
        return array_replace(['id' => 1, 'institut_id' => 1, 'person_id' => '1-100', 'beratung_id' => 'test-1',
            'planning_fingerprint' => str_repeat('a',64), 'tutor_person_id' => '1-200', 'title' => 'Einzelcoaching <Test>', 'agreed_minutes' => 180,
            'unit_minutes' => 45, 'version' => str_repeat('a',64), 'status' => 'active'], $overrides);
    }

    public function test_import_assigns_uvs_tutor_and_sends_one_actionable_inbox_message(): void
    {
        [, $tutor, , $t] = $this->people(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow()); $sync->importContract($this->importRow());
        $contract = CoachingContract::firstOrFail();
        $this->assertSame($t->id, $contract->tutor_person_id);
        $this->assertSame('1-200', $contract->uvs_tutor_person_id);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 2);
        $message = \App\Models\Message::where('to_user', $tutor->id)->firstOrFail();
        $this->assertSame($tutor->id, (int)$message->to_user);
        $this->assertSame(1, (int)$message->from_user);
        $this->assertSame('1', (string)$message->status);
        $this->assertStringContainsString('/coaching?contract='.$contract->id, $message->message);
        $this->assertStringContainsString('&lt;Test&gt;', $message->message);
        $this->assertNotNull($contract->fresh()->tutor_notified_at);
        $this->assertSame($tutor->id, $contract->fresh()->tutor_notified_user_id);
        $this->actingAs($tutor);
        \Livewire\Livewire::withQueryParams(['contract' => $contract->id])->test(\App\Livewire\Coaching\Planning::class)
            ->assertSet('contractId', $contract->id)->assertSee('Gesamtplan');
    }

    public function test_draft_is_assigned_and_notifies_both_before_activation(): void
    {
        $this->people(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow(['status' => 'draft']));
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 2);
        $this->assertNotNull(CoachingContract::first()->tutor_person_id);
        $this->assertNotNull(CoachingContract::first()->tutor_notified_at);
        $sync->importContract($this->importRow());
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_missing_tutor_account_is_retried_without_losing_uvs_assignment(): void
    {
        [, $tutor, , $t] = $this->people(); $sync = app(SyncService::class);
        Person::withoutEvents(fn () => $t->update(['user_id' => null]));
        $sync->importContract($this->importRow());
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame('1-200', CoachingContract::first()->uvs_tutor_person_id);
        Person::withoutEvents(fn () => $t->update(['user_id' => $tutor->id]));
        $sync->importContract($this->importRow()); $sync->importContract($this->importRow());
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_unmapped_or_foreign_tutor_never_receives_a_message(): void
    {
        [, , , $t] = $this->people(); $sync = app(SyncService::class);
        Person::withoutEvents(fn () => $t->update(['institut_id' => 2]));
        $sync->importContract($this->importRow());
        $this->assertNull(CoachingContract::first()->tutor_person_id);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 1);
        Person::withoutEvents(fn () => $t->update(['institut_id' => 1]));
        $sync->importContract($this->importRow());
        $this->assertSame($t->id, CoachingContract::first()->tutor_person_id);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_tutor_change_revokes_old_consent_and_notifies_new_tutor_once(): void
    {
        [, $tutor, , $t] = $this->people(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow()); $contract = CoachingContract::first();
        $plan = app(PlanService::class)->propose($contract->id, $tutor, 0, $this->items());
        app(PlanService::class)->confirm($contract->id, $tutor, 1);
        $newUser = User::create(['name' => 'Neuer Dozent', 'role' => 'tutor']);
        $newTutor = Person::withoutEvents(fn () => Person::create(['user_id' => $newUser->id, 'person_id' => '1-201', 'institut_id' => 1, 'role' => 'tutor']));
        $sync->importContract($this->importRow(['tutor_person_id' => '1-201', 'version' => str_repeat('b',64)]));
        $sync->importContract($this->importRow(['tutor_person_id' => '1-201', 'version' => str_repeat('b',64)]));
        $this->assertSame('superseded', $plan->fresh()->status);
        $this->assertSame(2, $contract->fresh()->revision);
        $this->assertSame($newTutor->id, $contract->fresh()->tutor_person_id);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertSame(2, \App\Models\Message::where('to_user', $newUser->id)->count());
        $this->assertFalse(CoachingContract::forUser($tutor)->exists());
    }

    public function test_missing_system_sender_does_not_mark_message_as_delivered(): void
    {
        $this->people(); User::whereKey(1)->delete(); $sync = app(SyncService::class);
        $sync->importContract($this->importRow());
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 0);
        $this->assertNull(CoachingContract::first()->tutor_notified_at);
        User::forceCreate(['id' => 1, 'name' => 'Schulnetz System', 'role' => 'admin']);
        $sync->importContract($this->importRow()); app(\App\Services\Coaching\NoticeService::class)->deliverPending(); $this->assertDatabaseCount('messages', 2);
    }

    public function test_inbox_link_cannot_open_another_persons_contract(): void
    {
        $this->people(); app(SyncService::class)->importContract($this->importRow());
        $this->actingAs(User::create(['name' => 'Fremd', 'role' => 'guest']));
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        \Livewire\Livewire::withQueryParams(['contract' => CoachingContract::first()->id])->test(\App\Livewire\Coaching\Planning::class);
    }

    public function test_participant_waits_for_first_tutor_plan_in_ui_and_cannot_start_by_direct_request(): void
    {
        [$participant, , $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $this->actingAs($participant);
        $page = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id);
        $page->assertSee('Ihr Dozent erstellt zuerst den Gesamtplan')
            ->assertDontSeeHtml('wire:click="edit"')->assertDontSeeHtml('wire:click="confirm"')
            ->call('edit')->assertHasErrors('plan')->assertSet('editing', false);
        // Public Livewire state must not bypass the service rule.
        $page->set('editing', true)->call('generateSchedule')->assertHasErrors('plan');
        try {
            app(PlanService::class)->propose($contract->id, $participant, 0, $this->items());
            $this->fail('Participant created the first plan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('plan', $e->errors());
        }
        $this->assertSame(0, $contract->fresh()->revision);
        $this->assertDatabaseCount('coaching_plans', 0);
        $this->assertDatabaseCount('coaching_notices', 0);
        $this->assertSame(0, CoachingOutbox::count());
    }

    public function test_tutor_starts_then_participant_can_counterpropose_and_both_can_confirm(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $this->actingAs($tutor);
        \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id)
            ->assertSee('Gesamtplan erstellen')->call('edit')->assertHasNoErrors()->assertSet('editing', true);
        $plans = app(PlanService::class);
        $first = $plans->propose($contract->id, $tutor, 0, $this->items());
        $this->actingAs($participant);
        \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id)
            ->assertDontSee('Ihr Dozent erstellt zuerst den Gesamtplan')
            ->assertSee('Gesamtplan ändern')->assertSee('Alle Termine bestätigen')
            ->call('edit')->assertHasNoErrors()->assertSet('editing', true);
        $counter = $plans->propose($contract->id, $participant, 1, $this->items());
        $this->assertSame('superseded', $first->fresh()->status);
        $plans->confirm($contract->id, $participant, $counter->revision);
        $this->assertNull($counter->fresh()->confirmed_at);
        $plans->confirm($contract->id, $tutor, $counter->revision);
        $this->assertNotNull($counter->fresh()->confirmed_at);
        $this->assertSame(1, CoachingOutbox::count());
    }

    public function test_new_contract_scope_or_tutor_requires_a_new_first_tutor_proposal(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $plans = app(PlanService::class);
        $first = $plans->propose($contract->id, $tutor, 0, $this->items());
        $first->update(['status' => 'superseded']);
        $contract->update(['contract_version' => str_repeat('b', 64)]);
        try {
            $plans->propose($contract->id, $participant, 1, $this->items());
            $this->fail('Old contract scope unlocked participant planning.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('plan', $e->errors()); }
        $next = $plans->propose($contract->id, $tutor, 1, $this->items());
        $replacementUser = User::create(['name' => 'Replacement tutor', 'role' => 'tutor']);
        $replacement = Person::withoutEvents(fn () => Person::create(['user_id' => $replacementUser->id,
            'person_id' => '1-300', 'institut_id' => 1, 'role' => 'tutor']));
        $contract->update(['tutor_person_id' => $replacement->id, 'uvs_tutor_person_id' => $replacement->person_id]);
        try {
            $plans->propose($contract->id, $participant, $next->revision, $this->items());
            $this->fail('Previous tutor unlocked participant planning.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('plan', $e->errors()); }
        $this->assertSame(2, $contract->fresh()->revision);
        $this->assertDatabaseCount('coaching_plans', 2);
    }

    public function test_preexisting_participant_first_plan_does_not_bypass_tutor_start(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $contract->update(['revision' => 1]);
        $old = $contract->plans()->create(['revision' => 1, 'contract_version' => $contract->contract_version,
            'participant_person_id' => $p->id, 'tutor_person_id' => $t->id, 'created_by' => $participant->id,
            'items' => app(PlanValidator::class)->validate($this->items(), $contract->agreed_minutes)]);
        try {
            app(PlanService::class)->confirm($contract->id, $participant, 1);
            $this->fail('Participant confirmed a plan before the tutor had proposed one.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('plan', $e->errors()); }
        $this->assertNull($old->fresh()->participant_confirmed_at);
        $new = app(PlanService::class)->propose($contract->id, $tutor, 1, $this->items());
        app(PlanService::class)->confirm($contract->id, $participant, $new->revision);
        $this->assertNotNull($new->fresh()->participant_confirmed_at);
    }

    public function test_planning_invitations_explain_tutor_first_to_both_recipients(): void
    {
        $this->people();
        app(SyncService::class)->importContract($this->importRow(['status' => 'draft']));
        $notices = \App\Models\CoachingNotice::where('kind', 'planning_requested')->get()->keyBy('recipient_role');
        $this->assertStringContainsString('erstellen Sie zuerst einen Gesamtplan', implode(' ', $notices['tutor']->content['lines']));
        $this->assertStringContainsString('Ihr Dozent erstellt zuerst', implode(' ', $notices['participant']->content['lines']));
        $this->assertStringContainsString('Sobald ein Vorschlag vorliegt', implode(' ', $notices['participant']->content['lines']));
    }

    public function test_whole_plan_requires_both_consents_and_remote_ack_before_standard_course_exists(): void
    {
        [$participant, $tutor, $p, $t] = $this->people();
        $contract = $this->contract($p, $t);
        $service = app(PlanService::class);
        $plan = $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $tutor, 1);
        $this->assertNull($plan->fresh()->confirmed_at);
        $this->assertSame(0, Course::count());
        $service->confirm($contract->id, $participant, 1);
        $this->assertNotNull($plan->fresh()->confirmed_at);
        $this->assertSame(1, CoachingOutbox::count());
        $this->assertFalse($contract->fresh()->startReady());
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->andReturn(['ok' => true, 'data' => ['revision' => 1]]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(1, app(SyncService::class)->sendPending());
        $this->assertSame(1, Course::count());
        $this->assertSame(2, CourseDay::count());
        $this->assertSame(1, DB::table('course_participant_enrollments')->count());
        $this->assertTrue($contract->fresh()->startReady());
        $this->assertSame(0, app(SyncService::class)->sendPending());
        $course = $contract->fresh()->course;
        $this->assertSame('coaching', $course->type);
        $this->assertNull($course->termin_id);
        $this->assertSame($course->id, app(CourseProjector::class)->project($contract->fresh(), $plan->fresh())->id);
        $this->assertSame(2, CourseDay::count());
    }

    public function test_a_new_plan_invalidates_previous_one_sided_consent_and_stale_confirmation(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $old = $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $tutor, 1);
        $new = $service->propose($contract->id, $participant, 1, $this->items());
        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertNull($new->tutor_confirmed_at);
        $this->expectException(ValidationException::class);
        $service->confirm($contract->id, $participant, 1);
    }

    public function test_partial_plan_and_overbooked_plan_are_rejected(): void
    {
        foreach ([90, 270] as $scope) {
            try { app(PlanValidator::class)->validate($this->items(), $scope); $this->fail('Wrong scope accepted.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('items', $e->errors()); }
        }
    }

    public function test_internal_overlap_and_dst_ambiguity_are_rejected(): void
    {
        $items = $this->items(); $items[1]['date'] = $items[0]['date'];
        try { app(PlanValidator::class)->validate($items, 180); $this->fail('Overlap accepted.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('überschneiden', $e->getMessage()); }
        foreach (['2026-03-29', '2026-10-25'] as $date) {
            $item = $this->items()[0]; $item['date'] = $date; $item['start'] = '02:15'; $item['end'] = '03:45';
            try { app(PlanValidator::class)->validate([$item], 90); $this->fail('Ambiguous/nonexistent time accepted.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('items', $e->errors()); }
        }
    }

    public function test_valid_plan_normalizes_utc_and_strips_untrusted_fields(): void
    {
        $items = $this->items(); $items[0]['admin_approved'] = true;
        $result = app(PlanValidator::class)->validate($items, 180);
        $this->assertSame('2026-09-21 07:00:00', $result[0]['starts_at']);
        $this->assertArrayNotHasKey('admin_approved', $result[0]);
    }

    public function test_foreign_user_cannot_read_or_confirm_another_contract(): void
    {
        [, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $outsider = User::create(['name' => 'Fremd', 'role' => 'guest']);
        $this->assertFalse(CoachingContract::forUser($outsider)->exists());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PlanService::class)->propose($contract->id, $outsider, 0, $this->items());
    }

    public function test_one_user_cannot_confirm_both_roles(): void
    {
        [$participant, , $p, $t] = $this->people();
        Person::withoutEvents(fn () => $t->update(['user_id' => $participant->id]));
        $contract = $this->contract($p, $t);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PlanService::class)->actor($contract, $participant);
    }

    public function test_uvs_failure_retains_outbox_and_does_not_release_course(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $service->propose($contract->id, $tutor, 0, $this->items()); $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        $api = \Mockery::mock(ApiUvsService::class); $api->shouldReceive('request')->once()->andReturn(['ok' => false, 'status' => 503]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->sendPending());
        $this->assertSame(0, Course::count());
        $event = CoachingOutbox::first(); $this->assertNull($event->sent_at); $this->assertSame(1, (int)$event->attempts);
    }

    public function test_changed_contract_cannot_be_released_from_old_confirmation(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $service->propose($contract->id, $tutor, 0, $this->items()); $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        $contract->update(['contract_version' => str_repeat('b', 64)]);
        $api = \Mockery::mock(ApiUvsService::class); $api->shouldNotReceive('request'); $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->sendPending()); $this->assertSame(0, Course::count());
    }

    public function test_confirmed_coaching_conflicts_with_second_coaching_for_same_tutor(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $service->propose($contract->id, $tutor, 0, $this->items()); $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        $second = $this->contract($p, $t, 2); $service->propose($second->id, $tutor, 0, $this->items());
        $this->expectException(ValidationException::class); $service->confirm($second->id, $tutor, 1);
    }

    public function test_opted_out_feed_row_does_not_create_a_new_case(): void
    {
        app(SyncService::class)->importContract(['id' => 10, 'institut_id' => 1, 'person_id' => '1-100', 'beratung_id' => 'test',
            'title' => 'Test', 'agreed_minutes' => 0, 'unit_minutes' => 45, 'version' => str_repeat('a',64), 'status' => 'inactive']);
        $this->assertSame(0, CoachingContract::count());
    }

    public function test_native_planning_view_renders_escaped_content_and_both_confirmations(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $items = $this->items(); $items[0]['topic'] = '<script>alert(1)</script>';
        $plan = app(PlanService::class)->propose($contract->id, $tutor, 0, $items);
        $this->actingAs($participant);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = view('livewire.coaching.planning', ['contracts' => collect([$contract->fresh()]), 'contract' => $contract->fresh(),
            'plan' => $plan->fresh(), 'revision' => 1, 'editing' => false, 'messages' => collect(), 'actor' => 'participant',
            'tutorStarted' => true, 'waitingForTutor' => false])->render();
        $this->assertStringContainsString('Alle Termine bestätigen', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('180 Minuten', $html);
    }

    public function test_standard_documentation_keeps_its_course_day_and_schedule_is_immutable(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $plan = $service->propose($contract->id, $tutor, 0, $this->items());
        $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        app(CourseProjector::class)->project($contract->fresh(), $plan->fresh());
        $day = CourseDay::first(); $day->update(['notes' => 'Normale Schulnetz-Dokumentation', 'note_status' => 1]);
        $this->assertSame('Normale Schulnetz-Dokumentation', $day->fresh()->notes);
        $this->assertSame($day->id, CourseDay::first()->id);
        $this->expectException(ValidationException::class);
        $day->update(['date' => '2026-09-22']);
    }

    public function test_ec_only_person_remains_a_valid_portal_identity_without_regular_contract(): void
    {
        [, , $person] = $this->people();
        $person->statusdata = ['coaching_contracts' => [['status' => 'active', 'valid_until' => null, 'cancelled_on' => null]]];
        $this->assertTrue($person->hasPortalIdentity()); $this->assertTrue($person->hasValidParticipantContract());
        $this->assertSame('guest', $person->resolvePortalRoleCandidate());
        \App\Models\Setting::setValue('coaching', 'enabled', false);
        $this->assertFalse(\App\Services\Coaching\Access::hasActiveStatus($person->statusdata));
    }

    public function test_standard_enrollment_scope_includes_coaching_without_exposing_foreign_courses(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t); $service = app(PlanService::class);
        $plan = $service->propose($contract->id, $tutor, 0, $this->items()); $service->confirm($contract->id, $tutor, 1); $service->confirm($contract->id, $participant, 1);
        $course = app(CourseProjector::class)->project($contract->fresh(), $plan->fresh());
        $p->statusdata = ['vertraege' => [['teilnehmer_id' => 'old', 'vertrag_beginn' => '2020-01-01', 'vertrag_ende' => '2020-12-31']]];
        $query = DB::table('course_participant_enrollments as cpe');
        \App\Support\CurrentParticipantCourseScope::applyForPerson($query, $p, 'cpe', null);
        $this->assertSame([$course->id], $query->pluck('course_id')->all());
        $this->assertSame($p->id, \App\Services\Coaching\Access::participantForCourse($participant, $course->id)->id);
        $this->assertNull(\App\Services\Coaching\Access::participantForCourse($tutor, $course->id));
    }

    public function test_automatic_distribution_covers_all_units_and_shortens_only_the_last_slot(): void
    {
        [, , $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $contract->update(['agreed_minutes' => 225]);
        $options = ['start_date' => '2026-09-21', 'weekdays' => [1, 3], 'start_time' => '09:00',
            'units_per_appointment' => 2, 'every_weeks' => 1, 'topic' => 'Bewerbung', 'format' => 'online', 'location' => 'Online'];
        $items = app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, $options);
        $this->assertSame(['2026-09-21', '2026-09-23', '2026-09-28'], array_column($items, 'date'));
        $this->assertSame([90, 90, 45], array_column($items, 'minutes'));
        $this->assertSame(['10:30', '10:30', '09:45'], array_column($items, 'end'));
        $this->assertSame(3, count(array_unique(array_column($items, 'id'))));
        $this->assertDatabaseCount('coaching_plans', 0);
        app(\App\Services\Coaching\NoticeService::class)->deliverPending();
        $this->assertDatabaseCount('messages', 0);
        $contract->agreed_minutes = 180; $contract->unit_minutes = 60;
        $items = app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, $options);
        $this->assertSame([120, 60], array_column($items, 'minutes'));
    }

    public function test_automatic_distribution_anchors_multiweek_rhythm_to_the_start_week(): void
    {
        [, , $p, $t] = $this->people(); $contract = $this->contract($p, $t); $contract->agreed_minutes = 135;
        $items = app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, [
            'start_date' => '2026-09-22', 'weekdays' => ['1', '3'], 'start_time' => '10:00',
            'units_per_appointment' => 1, 'every_weeks' => 2, 'topic' => 'Ziele', 'format' => 'presence', 'location' => 'Raum 1']);
        $this->assertSame(['2026-09-23', '2026-10-05', '2026-10-07'], array_column($items, 'date'));
        $this->assertSame(135, array_sum(array_column($items, 'minutes')));
    }

    public function test_invalid_distribution_rules_and_contract_windows_are_rejected(): void
    {
        [, , $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $options = ['start_date' => '2026-09-21', 'weekdays' => [1], 'start_time' => '09:00',
            'units_per_appointment' => 2, 'every_weeks' => 1, 'topic' => 'Ziele', 'format' => 'online', 'location' => 'Online'];
        foreach ([['weekdays' => []], ['weekdays' => [1, 1]], ['weekdays' => [8]], ['units_per_appointment' => 0],
            ['units_per_appointment' => 1.5], ['units_per_appointment' => 17], ['every_weeks' => 0],
            ['start_date' => '2026-02-30'], ['start_date' => '2026-09-16'], ['start_time' => '23:30'],
            ['start_date' => '2026-10-25', 'weekdays' => [7], 'start_time' => '02:15']] as $override) {
            try { app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, array_replace($options, $override)); $this->fail('Invalid distribution accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $contract->valid_until = '2026-09-25';
        try { app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, $options); $this->fail('Incomplete schedule accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('schedule.start_date', $e->errors()); }
        $contract->valid_until = null; $contract->valid_from = '2026-09-22';
        try { app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, $options); $this->fail('Date before contract accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('schedule.start_date', $e->errors()); }
        $contract->valid_from = null; $contract->agreed_minutes = 256 * 90;
        try { app(\App\Services\Coaching\ScheduleGenerator::class)->generate($contract, $options); $this->fail('Too many slots accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('schedule.units_per_appointment', $e->errors()); }
    }

    public function test_modal_generation_is_a_draft_until_proposed_and_cancel_preserves_the_published_plan(): void
    {
        [, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $plan = app(PlanService::class)->propose($contract->id, $tutor, 0, $this->items());
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id)
            ->call('edit')->assertSet('editing', true)->assertSee('Automatisch verteilen')
            ->set('schedule.start_date', '2026-09-21')->set('schedule.weekdays', [1, 3])->call('generateSchedule')->assertHasNoErrors();
        $this->assertSame(['2026-09-21', '2026-09-23'], array_column($component->get('items'), 'date'));
        $this->assertDatabaseCount('coaching_plans', 1);
        $component->call('cancelEditing')->assertSet('editing', false);
        $this->assertSame($plan->items, $component->get('items'));
        $component->call('edit')->set('schedule.start_date', '2026-09-21')->call('generateSchedule')->call('propose')->assertHasNoErrors()->assertSet('editing', false);
        $this->assertDatabaseCount('coaching_plans', 2);
        $this->assertNull($contract->fresh()->latestPlan->confirmed_at);
        $component->call('composeMessage')->assertSet('composing', true)->set('message', 'Entwurf')->call('cancelMessage')->assertSet('message', '')->assertSet('composing', false);
        $this->assertDatabaseCount('coaching_messages', 0);
    }

    public function test_failed_generation_keeps_the_manual_draft_and_confirmed_plans_cannot_be_regenerated(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        app(PlanService::class)->propose($contract->id, $tutor, 0, $this->items());
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id)->call('edit');
        $before = $component->get('items');
        $component->set('schedule.weekdays', [])->call('generateSchedule')->assertHasErrors('schedule.weekdays');
        $this->assertSame($before, $component->get('items'));
        app(PlanService::class)->confirm($contract->id, $tutor, 1);
        app(PlanService::class)->confirm($contract->id, $participant, 1);
        $component->call('generateSchedule')->assertForbidden();
        $this->assertSame('confirmed', $contract->fresh()->latestPlan->status);
    }

    public function test_two_step_editor_validates_before_advancing_and_only_publishes_from_review(): void
    {
        [, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)
            ->call('select', $contract->id)->call('edit')->assertSet('editorStep', 1)
            ->assertSee('Verteilung festlegen')->assertSee('Termine prüfen')
            ->set('schedule.start_date', '2026-09-21')->set('schedule.weekdays', [])
            ->call('submitEditor')->assertHasErrors('schedule.weekdays')->assertSet('editorStep', 1)
            ->set('schedule.weekdays', [1,3])->call('submitEditor')->assertHasNoErrors()->assertSet('editorStep', 2);
        $draft = $component->get('items');
        $this->assertDatabaseCount('coaching_plans', 0);
        $component->call('scheduleSettings')->assertSet('editorStep', 1);
        $this->assertSame($draft, $component->get('items'));
        $component->call('reviewSchedule')->assertSet('editorStep', 2);
        $this->assertSame($draft, $component->get('items'));
        $component->call('submitEditor')->assertHasNoErrors()->assertSet('editing', false)->assertSet('editorStep', 1);
        $this->assertDatabaseCount('coaching_plans', 1);
        $this->assertNull($contract->fresh()->latestPlan->confirmed_at);
        $component->call('edit')->call('propose')->assertForbidden();
        $this->assertDatabaseCount('coaching_plans', 1);
    }

    public function test_calendar_and_cards_keep_the_same_draft_across_views_and_month_changes(): void
    {
        [, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)
            ->call('select', $contract->id)->call('edit')->set('schedule.start_date', '2026-09-21')->call('generateSchedule')
            ->assertSet('planView', 'list')->assertSet('expandedSlotId', null);
        $draft = $component->get('items'); $id = $draft[0]['id'];
        $component->call('toggleSlot', $id)->assertSet('expandedSlotId', $id)->set('items.0.topic', 'Individuelles Ziel')
            ->call('changePlanView', 'calendar')->call('selectPlanDate', '2026-09-21')
            ->call('movePlanMonth', 1)->assertSet('calendarMonth', '2026-10')->assertSet('calendarDate', '2026-10-01')
            ->call('movePlanMonth', -1)->assertSet('calendarDate', '2026-09-21')
            ->call('toggleSlot', $id)->set('items.0.date', '2026-10-02')->assertSet('calendarMonth', '2026-10')->assertSet('calendarDate', '2026-10-02')
            ->call('changePlanView', 'list')->assertSet('items.0.topic', 'Individuelles Ziel')->assertSet('items.1', $draft[1]);
        $component->call('add')->assertSet('expandedSlotId', $component->get('items')[2]['id']);
        $component->call('remove', 2)->assertSet('expandedSlotId', null);
        $this->assertCount(2, $component->get('items'));
        $this->assertDatabaseCount('coaching_plans', 0);
        $component->call('cancelEditing')->assertSet('items', [])->assertSet('planView', 'list');
    }

    public function test_calendar_groups_multiple_events_and_handles_leap_and_year_boundaries(): void
    {
        $items = $this->items();
        $items[0]['date'] = $items[1]['date'] = '2028-02-29';
        $items[0]['start'] = '13:00'; $items[1]['start'] = '09:00';
        $calendar = \App\Services\Coaching\ScheduleReview::calendar($items, '2028-02');
        $this->assertSame('2028-02-29', $calendar['selected']);
        $this->assertCount(35, $calendar['days']);
        $this->assertSame('2028-01-31', $calendar['days'][0]['date']);
        $day = collect($calendar['days'])->firstWhere('date', '2028-02-29');
        $this->assertCount(2, $day['events']); $this->assertSame('09:00', $day['events'][0]['start']);
        $this->assertTrue($day['selected']);
        $calendar = \App\Services\Coaching\ScheduleReview::calendar([], '2026-12');
        $this->assertSame('2027-01-03', end($calendar['days'])['date']);
        $this->assertNull(\App\Services\Coaching\ScheduleReview::date('2026-02-30'));
        $this->assertSame('Datum festlegen', \App\Services\Coaching\ScheduleReview::card(['date' => ''], 45)['date']);
    }

    public function test_invalid_collapsed_appointment_is_revealed_and_no_plan_is_published(): void
    {
        [, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        $this->actingAs($tutor);
        $component = \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)
            ->call('select', $contract->id)->call('edit')->set('schedule.start_date', '2026-09-21')->call('generateSchedule');
        $id = $component->get('items')[1]['id'];
        $component->set('items.1.topic', '')->call('changePlanView', 'calendar')->call('selectPlanDate', '2026-10-01')
            ->call('submitEditor')->assertHasErrors('items.1.topic')->assertSet('planView', 'list')->assertSet('expandedSlotId', $id);
        $this->assertDatabaseCount('coaching_plans', 0);
    }

    public function test_livewire_rechecks_ownership_and_confirmation_state_on_actions(): void
    {
        [$participant, $tutor, $p, $t] = $this->people(); $contract = $this->contract($p, $t);
        app(PlanService::class)->propose($contract->id, $tutor, 0, $this->items());
        $this->actingAs($participant);
        \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id)
            ->assertSee('Alle Termine bestätigen')->call('confirm')->assertHasNoErrors();
        $this->assertNotNull($contract->fresh()->latestPlan->participant_confirmed_at);
        $outsider = User::create(['name' => 'Fremd', 'role' => 'guest']);
        $this->actingAs($outsider);
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        \Livewire\Livewire::test(\App\Livewire\Coaching\Planning::class)->call('select', $contract->id);
    }
}
