<?php

namespace Tests\Feature;

use App\Services\Maintenance\RetireEmptyDuplicateUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RetireEmptyDuplicateUserTest extends TestCase
{
    private string $temporaryStorage;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true, 'cache.default' => 'array',
                'queue.default' => 'sync', 'session.driver' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $this->temporaryStorage = sys_get_temp_dir().'/schulnetz-empty-orphan-test-'.bin2hex(random_bytes(10));
        $this->app->useStoragePath($this->temporaryStorage);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role');
            $table->string('password');
            $table->string('profile_photo_path')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('person_id')->unique();
            $table->string('email_priv');
            $table->softDeletes();
        });
        Schema::create('report_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id');
        });
        Schema::create('mails', function (Blueprint $table) {
            $table->id();
            $table->json('recipients');
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->longText('payload');
        });
        Schema::create('morph_records', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
        });
        DB::table('users')->insert([
            ['id' => 9992, 'name' => 'Fixture Participant', 'email' => 'old@example.test', 'role' => 'guest',
                'password' => 'original-password-hash', 'created_at' => '2026-09-01 00:00:00'],
            ['id' => 9993, 'name' => 'Fixture Participant', 'email' => 'new@example.test', 'role' => 'guest',
                'password' => 'duplicate-password-hash', 'created_at' => '2026-10-01 00:00:00'],
        ]);
        DB::table('persons')->insert(['id' => 7, 'user_id' => 9992, 'person_id' => '1-0042', 'email_priv' => 'new@example.test']);
        DB::table('report_books')->insert(['id' => 8, 'user_id' => 9992, 'description' => 'Existing original report book']);
        DB::table('course_participant_enrollments')->insert(['id' => 9, 'person_id' => 7]);
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryStorage) && str_starts_with($this->temporaryStorage, sys_get_temp_dir().'/schulnetz-empty-orphan-test-')) {
            (new Filesystem)->deleteDirectory($this->temporaryStorage);
        }
        parent::tearDown();
    }

    public function test_default_command_is_read_only_and_does_not_expose_identity_or_credentials(): void
    {
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $this->artisan('users:retire-empty-duplicate', ['keep' => 9992, 'duplicate' => 9993, 'person' => '1-0042'])
            ->expectsOutputToContain('"eligible": true')->doesntExpectOutputToContain('old@example.test')
            ->doesntExpectOutputToContain('original-password-hash')->assertSuccessful();
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertDirectoryDoesNotExist($this->temporaryStorage);
    }

    public function test_only_orphan_row_is_retired_after_verified_encrypted_backup(): void
    {
        $service = new RetireEmptyDuplicateUser;
        $keep = (array) DB::table('users')->where('id', 9992)->first();
        $duplicate = (array) DB::table('users')->where('id', 9993)->first();
        $person = (array) DB::table('persons')->first();
        $books = DB::table('report_books')->get()->toJson();
        $enrollments = DB::table('course_participant_enrollments')->get()->toJson();
        $preview = $service->preview(9992, 9993, '1-0042');
        $result = $service->execute(9992, 9993, '1-0042', $preview['hash'], true);
        $this->assertTrue($result['retired']);
        $this->assertFalse(DB::table('users')->where('id', 9993)->exists());
        $this->assertSame($keep, (array) DB::table('users')->where('id', 9992)->first());
        $this->assertSame($person, (array) DB::table('persons')->first());
        $this->assertSame($books, DB::table('report_books')->get()->toJson());
        $this->assertSame($enrollments, DB::table('course_participant_enrollments')->get()->toJson());
        $ciphertext = file_get_contents($result['backup_path']);
        $this->assertStringNotContainsString('duplicate-password-hash', $ciphertext);
        $backup = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($keep, $backup['state']['keep']);
        $this->assertSame($duplicate, $backup['state']['duplicate']);
        $this->assertSame($person, $backup['state']['person']);
    }

    public function test_stale_preview_and_unpaused_writes_are_rejected(): void
    {
        $service = new RetireEmptyDuplicateUser;
        $preview = $service->preview(9992, 9993, '1-0042');
        foreach ([false, true] as $paused) {
            if ($paused) {
                DB::table('users')->where('id', 9993)->update(['name' => 'Changed since preview']);
            }
            try {
                $service->execute(9992, 9993, '1-0042', $preview['hash'], $paused);
                $this->fail('Expected safety rejection.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString($paused ? 'Vorschau' : 'pausieren', $error->getMessage());
            }
        }
        $this->assertSame(2, DB::table('users')->count());
        $this->assertDirectoryDoesNotExist($this->temporaryStorage);
    }

    public function test_referenced_orphan_is_blocked_including_soft_deleted_persons_and_unknown_foreign_keys(): void
    {
        Schema::create('legacy_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unexpected_owner')->constrained('users')->cascadeOnDelete();
        });
        DB::table('legacy_links')->insert(['unexpected_owner' => 9993]);
        DB::table('persons')->insert(['id' => 10, 'user_id' => 9993, 'person_id' => '2-0042',
            'email_priv' => 'other@example.test', 'deleted_at' => '2026-10-01 00:00:00']);
        $service = new RetireEmptyDuplicateUser;
        $preview = $service->preview(9992, 9993, '1-0042');
        $this->assertFalse($preview['eligible']);
        $this->assertArrayHasKey('legacy_links.unexpected_owner', $preview['blockers']);
        $this->assertArrayHasKey('persons.user_id', $preview['blockers']);
        try {
            $service->execute(9992, 9993, '1-0042', $preview['hash'], true);
            $this->fail('Expected reference rejection.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Verweise', $error->getMessage());
        }
        $this->assertSame(2, DB::table('persons')->count());
        $this->assertSame(1, DB::table('legacy_links')->count());
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_json_queue_email_and_polymorphic_references_are_blocked_without_unserialization(): void
    {
        DB::table('mails')->insert(['recipients' => json_encode([['user_id' => 9993]])]);
        DB::table('jobs')->insert(['payload' => '{"data":{"command":"serialized opaque fixture mentions 9993"}}']);
        DB::table('morph_records')->insert(['owner_type' => \App\Models\User::class, 'owner_id' => 9993]);
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
        });
        DB::table('password_reset_tokens')->insert(['email' => 'new@example.test', 'token' => 'fixture-reset']);
        $preview = (new RetireEmptyDuplicateUser)->preview(9992, 9993, '1-0042');
        $this->assertFalse($preview['eligible']);
        foreach (['mails.text_candidate', 'jobs.text_candidate', 'morph_records.owner_type',
            'password_reset_tokens.text_candidate'] as $key) {
            $this->assertArrayHasKey($key, $preview['blockers']);
        }
    }

    public function test_backup_failure_preserves_all_rows(): void
    {
        $service = new class extends RetireEmptyDuplicateUser {
            protected function backup(array $state): string
            {
                throw new RuntimeException('Synthetic backup failure');
            }
        };
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $preview = $service->preview(9992, 9993, '1-0042');
        try {
            $service->execute(9992, 9993, '1-0042', $preview['hash'], true);
            $this->fail('Expected backup failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic backup failure', $error->getMessage());
        }
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame(1, DB::table('report_books')->count());
    }

    public function test_user_delete_trigger_and_protected_or_misidentified_accounts_are_rejected(): void
    {
        DB::unprepared('CREATE TRIGGER user_delete_trigger AFTER DELETE ON users BEGIN DELETE FROM report_books; END');
        $service = new RetireEmptyDuplicateUser;
        $this->assertArrayHasKey('users.triggers', $service->preview(9992, 9993, '1-0042')['blockers']);
        foreach ([[1, 9993, '1-0042'], [9993, 9992, '1-0042'], [9992, 9993, 'wrong-person']] as $arguments) {
            try {
                $service->preview(...$arguments);
                $this->fail('Expected identity rejection.');
            } catch (RuntimeException) {
                $this->assertSame(2, DB::table('users')->count());
            }
        }
        $this->assertSame(1, DB::table('report_books')->count());
    }

    public function test_unrelated_orphan_email_and_nonempty_profile_are_rejected(): void
    {
        $service = new RetireEmptyDuplicateUser;
        DB::table('users')->where('id', 9993)->update(['profile_photo_path' => 'private-photo.png']);
        $this->assertArrayHasKey('users.profile_photo_path', $service->preview(9992, 9993, '1-0042')['blockers']);
        DB::table('users')->where('id', 9993)->update(['email' => 'unrelated@example.test']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kontaktadresse');
        $service->preview(9992, 9993, '1-0042');
    }

    public function test_database_error_after_backup_rolls_back_and_keeps_restoration_snapshot(): void
    {
        $service = new RetireEmptyDuplicateUser;
        $preview = $service->preview(9992, 9993, '1-0042');
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'delete from "users"')) {
                throw new RuntimeException('Synthetic database failure');
            }
        });
        try {
            $service->execute(9992, 9993, '1-0042', $preview['hash'], true);
            $this->fail('Expected database failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic database failure', $error->getMessage());
        }
        $this->assertSame(2, DB::table('users')->count());
        $this->assertSame(1, DB::table('report_books')->count());
        $files = glob($this->temporaryStorage.'/app/private/maintenance/user-orphan-snapshots/*.enc');
        $this->assertCount(1, $files);
        $backup = json_decode(Crypt::decryptString(file_get_contents($files[0])), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(9993, $backup['state']['duplicate']['id']);
    }
}
