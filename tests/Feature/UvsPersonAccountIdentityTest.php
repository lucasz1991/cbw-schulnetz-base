<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Person;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\PersonUvsSyncService;
use App\Services\Coaching\NoticeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class UvsPersonAccountIdentityTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array',
                'session.driver' => 'array', 'activitylog.dispatch_enabled' => false,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        (require __DIR__.'/../../database/migrations/2014_10_12_000000_create_users_table.php')->up();
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->integer('status')->nullable();
        });
        (require __DIR__.'/../../database/migrations/2025_09_10_152937_create_persons_table.php')->up();
        Schema::table('persons', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('teilnehmer_id')->nullable();
            $table->string('teilnehmer_nr')->nullable();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id');
            $table->integer('course_id');
            $table->text('results')->nullable();
        });
        Notification::fake();
        Bus::fake();
        Password::shouldReceive('createToken')->andReturn('synthetic-reset-token');
        $this->mock(NoticeService::class)->shouldReceive('linkRegisteredUser')->zeroOrMoreTimes();
    }

    public function test_changed_uvs_email_refreshes_person_but_keeps_account_credentials_and_assignments(): void
    {
        $user = $this->user();
        $person = $this->person($user);
        $before = $user->fresh()->getRawOriginal();
        DB::table('course_participant_enrollments')->insert([
            'person_id' => $person->id, 'course_id' => 12, 'results' => '{"grade":1}',
        ]);
        $this->api([$this->payload()]);

        $error = $this->registerError();

        $this->assertStringContainsString('bereits ein Schulnetz-Konto', $error);
        $this->assertStringNotContainsString($user->email, $error);
        $this->assertSame(1, User::count());
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame($user->id, $person->fresh()->user_id);
        $this->assertSame('new@example.test', $person->fresh()->email_priv);
        $this->assertSame('training@example.test', $person->fresh()->email_cbw);
        $this->assertSame(['sentinel' => 'program'], $person->fresh()->programdata);
        $this->assertSame(['sentinel' => 'status'], $person->fresh()->statusdata);
        $this->assertDatabaseHas('course_participant_enrollments', ['person_id' => $person->id, 'results' => '{"grade":1}']);
        Notification::assertNothingSent();
    }

    public function test_soft_deleted_person_still_blocks_duplicate_and_stays_deleted(): void
    {
        $user = $this->user();
        $person = $this->person($user, ['deleted_at' => now()]);
        $this->api([$this->payload()]);
        $this->registerError();

        $this->assertSame(1, User::count());
        $this->assertTrue($person->fresh()->trashed());
        $this->assertSame('new@example.test', $person->fresh()->email_priv);
        Notification::assertNothingSent();
    }

    public function test_multiple_existing_accounts_are_rejected_without_contact_changes(): void
    {
        $left = $this->person($this->user());
        $right = $this->person($this->user('other@example.test'), ['person_id' => '2-SYNTHETIC']);
        $this->api([$this->payload(), $this->payload(['person_id' => $right->person_id])]);

        $this->assertStringContainsString('Klärung', $this->registerError());
        $this->assertSame(2, User::count());
        $this->assertSame('old-contact@example.test', $left->fresh()->email_priv);
        $this->assertSame('old-contact@example.test', $right->fresh()->email_priv);
        Notification::assertNothingSent();
    }

    public function test_missing_contact_values_do_not_clear_existing_contact(): void
    {
        $person = $this->person($this->user());
        $payload = $this->payload();
        unset($payload['email_priv']);
        $payload['email_cbw'] = null;
        $this->api([$payload]);
        $this->registerError();

        $this->assertSame('old-contact@example.test', $person->fresh()->email_priv);
        $this->assertSame('old-training@example.test', $person->fresh()->email_cbw);
    }

    public function test_dangling_account_reference_is_rejected_without_a_new_user(): void
    {
        Schema::disableForeignKeyConstraints();
        $person = $this->person(null, ['user_id' => 999]);
        Schema::enableForeignKeyConstraints();
        $this->api([$this->payload()]);

        $this->assertStringContainsString('Klärung', $this->registerError());
        $this->assertSame(0, User::count());
        $this->assertSame(999, $person->fresh()->user_id);
        $this->assertSame('old-contact@example.test', $person->fresh()->email_priv);
        Notification::assertNothingSent();
    }

    public function test_unlinked_archived_person_does_not_create_an_inaccessible_account(): void
    {
        $person = $this->person(null, ['deleted_at' => now()]);
        $this->api([$this->payload()]);

        $this->assertStringContainsString('archiviert', $this->registerError());
        $this->assertSame(0, User::count());
        $this->assertTrue($person->fresh()->trashed());
        $this->assertNull($person->fresh()->user_id);
        Notification::assertNothingSent();
    }

    public function test_new_person_can_register_once_and_receives_password_notification(): void
    {
        $api = $this->api([$this->payload()]);
        $api->shouldReceive('getPersonStatus')->once()->andReturn($this->statusResponse());

        $this->registrationComponent()->register();

        $this->assertSame(1, User::count());
        $user = User::firstOrFail();
        $this->assertSame('new@example.test', $user->email);
        $this->assertDatabaseHas('persons', ['person_id' => '1-SYNTHETIC', 'user_id' => $user->id]);
        Notification::assertSentTo($user, SetPasswordNotification::class);
        Notification::assertCount(1);
    }

    public function test_existing_unlinked_person_keeps_its_id_and_assignments_on_registration(): void
    {
        $person = $this->person(null);
        DB::table('course_participant_enrollments')->insert(['person_id' => $person->id, 'course_id' => 7]);
        $api = $this->api([$this->payload()]);
        $api->shouldReceive('getPersonStatus')->once()->andReturn($this->statusResponse());
        $this->registrationComponent()->register();

        $this->assertSame(1, Person::withTrashed()->count());
        $this->assertSame(User::firstOrFail()->id, $person->fresh()->user_id);
        $this->assertDatabaseHas('course_participant_enrollments', ['person_id' => $person->id, 'course_id' => 7]);
    }

    public function test_transaction_rechecks_identity_linked_during_status_request(): void
    {
        $api = $this->api([$this->payload()]);
        $api->shouldReceive('getPersonStatus')->once()->andReturnUsing(function () {
            $this->person($this->user());

            return $this->statusResponse();
        });
        $this->registerError();

        $this->assertSame(1, User::count());
        $this->assertSame('original-login@example.test', User::firstOrFail()->email);
        $this->assertSame('new@example.test', Person::firstOrFail()->email_priv);
        Notification::assertNothingSent();
    }

    public function test_missing_api_identity_is_rejected_before_account_creation(): void
    {
        $this->api([['vorname' => 'Synthetic', 'email_priv' => 'new@example.test']]);
        $this->assertStringContainsString('eindeutig', $this->registerError());
        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }

    public function test_failed_registration_rolls_back_user_and_sends_no_password_mail(): void
    {
        $api = $this->api([$this->payload()]);
        $api->shouldReceive('getPersonStatus')->once()->andReturn($this->statusResponse());
        $this->mock(NoticeService::class)->shouldReceive('linkRegisteredUser')->once()
            ->andThrow(new \RuntimeException('synthetic registration failure'));
        try {
            $this->registrationComponent()->register();
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('synthetic registration failure', $error->getMessage());
        }
        $this->assertSame(0, User::count());
        $this->assertSame(0, Person::withTrashed()->count());
        Notification::assertNothingSent();
    }

    public function test_login_with_changed_contact_email_never_authenticates_or_reveals_original_login(): void
    {
        $user = $this->user();
        $this->person($user);
        $this->api([$this->payload()]);
        $login = new Login;
        $login->email = 'new@example.test';
        try {
            $login->login('wrong-password');
            $this->fail('Expected validation error');
        } catch (ValidationException $error) {
            $message = $error->errors()['email'][0];
            $this->assertStringContainsString('bisherige Schulnetz-Anmeldeadresse', $message);
            $this->assertStringNotContainsString('registriere', $message);
            $this->assertStringNotContainsString($user->email, $message);
        }
        $this->assertFalse(Auth::check());
        Notification::assertNothingSent();
    }

    public function test_regular_participant_sync_refreshes_contact_without_changing_login(): void
    {
        $user = $this->user();
        $person = $this->person($user);
        $api = Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getParticipantAndQualiprogrambyId')->once()->with('1-SYNTHETIC', null)->andReturn([
            'ok' => true, 'data' => ['person' => $this->payload(), 'quali_data' => ['teilnehmer_id' => '1-TN']],
        ]);
        $this->app->instance(ApiUvsService::class, $api);

        (new PersonUvsSyncService)->sync($person, $this->statusResponse()['data']['data']);

        $this->assertSame('new@example.test', $person->fresh()->email_priv);
        $this->assertSame('training@example.test', $person->fresh()->email_cbw);
        $this->assertSame($user->id, $person->fresh()->user_id);
        $this->assertSame('original-login@example.test', $user->fresh()->email);
        $this->assertSame(1, User::count());
    }

    public function test_incomplete_original_account_still_receives_activation_at_original_login(): void
    {
        $user = $this->user();
        $this->person($user);
        $api = Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getParticipantbyMail')->once()->with($user->email)
            ->andReturn(['ok' => true, 'data' => ['persons' => [$this->payload()]]]);
        $this->app->instance(ApiUvsService::class, $api);
        $login = new Login;
        $login->email = $user->email;
        $login->login('wrong-password');

        Notification::assertSentTo($user, SetPasswordNotification::class);
        Notification::assertCount(1);
        $this->assertFalse(Auth::check());
        $this->assertSame('original-login@example.test', $user->fresh()->email);
    }

    public function test_sync_does_not_apply_contact_from_another_identity(): void
    {
        $person = $this->person($this->user());
        $api = Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getParticipantAndQualiprogrambyId')->once()->andReturn([
            'ok' => true, 'data' => ['person' => $this->payload(['person_id' => '2-OTHER']), 'quali_data' => ['teilnehmer_id' => '1-TN']],
        ]);
        $this->app->instance(ApiUvsService::class, $api);
        (new PersonUvsSyncService)->sync($person, $this->statusResponse()['data']['data']);

        $this->assertSame('old-contact@example.test', $person->fresh()->email_priv);
        $this->assertSame('old-training@example.test', $person->fresh()->email_cbw);
    }

    private function user(string $email = 'original-login@example.test'): User
    {
        return User::create(['name' => 'Synthetic Participant', 'email' => $email, 'password' => Hash::make('existing-password'), 'role' => 'guest']);
    }

    private function person(?User $user, array $overrides = []): Person
    {
        return Person::withoutEvents(fn () => Person::forceCreate(array_merge([
            'user_id' => $user?->id, 'person_id' => '1-SYNTHETIC', 'person_nr' => 'SYNTHETIC', 'institut_id' => '1',
            'vorname' => 'Synthetic', 'nachname' => 'Participant', 'role' => 'guest',
            'email_priv' => 'old-contact@example.test', 'email_cbw' => 'old-training@example.test',
            'programdata' => ['sentinel' => 'program'], 'statusdata' => ['sentinel' => 'status'],
        ], $overrides)));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'person_id' => '1-SYNTHETIC', 'person_nr' => 'SYNTHETIC', 'institut_id' => '1',
            'vorname' => 'Synthetic', 'nachname' => 'Participant',
            'email_priv' => 'new@example.test', 'email_cbw' => 'training@example.test',
        ], $overrides);
    }

    private function statusResponse(): array
    {
        return ['ok' => true, 'data' => ['data' => ['status' => 'Teilnehmer', 'teilnehmer_id' => '1-TN', 'teilnehmer_nr' => 'TN']]];
    }

    private function api(array $payloads): \Mockery\MockInterface
    {
        $api = Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('getParticipantbyMail')->once()->with('new@example.test')
            ->andReturn(['ok' => true, 'data' => ['persons' => $payloads]]);
        $this->app->instance(ApiUvsService::class, $api);

        return $api;
    }

    private function registrationComponent(): Register
    {
        $component = new Register;
        $component->email = 'new@example.test';

        return $component;
    }

    private function registerError(): string
    {
        try {
            $this->registrationComponent()->register();
            $this->fail('Expected validation error');
        } catch (ValidationException $error) {
            return $error->errors()['email'][0];
        }
    }
}
