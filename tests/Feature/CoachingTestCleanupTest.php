<?php

namespace Tests\Feature;

use App\Jobs\DeliverCoachingNotices;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\FilePool;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\Coaching\NoticeService;
use App\Services\Coaching\SyncService;
use App\Services\Coaching\TestCleanupService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class CoachingTestCleanupTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true, 'cache.default' => 'array',
                'queue.default' => 'sync', 'session.driver' => 'array']);
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
        Storage::fake('private');
        Storage::fake('public');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('role');
            $t->string('profile_photo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('person_id');
            $t->integer('institut_id');
            $t->string('role');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->integer('from_user');
            $t->integer('to_user');
            $t->text('message')->nullable();
        });
        foreach (['2025_09_10_152938_create_courses_table.php', '2025_09_10_152939_create_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php', '2026_09_17_080000_create_coaching_planning_tables.php',
            '2026_09_17_110000_add_uvs_tutor_to_coaching_contracts.php', '2026_09_22_100000_create_coaching_notices.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        foreach (['course_results', 'course_ratings', 'course_material_acknowledgements', 'report_books'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
                $t->integer('person_id')->nullable();
                $t->integer('user_id')->nullable();
                $t->integer('participant_id')->nullable();
                if ($table === 'course_material_acknowledgements') {
                    $t->string('signature_path')->nullable();
                }
            });
        }
        Schema::create('report_book_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_day_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('admin_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('context_type');
            $t->integer('context_id');
        });
        Schema::create('file_pools', function (Blueprint $t) {
            $t->id();
            $t->string('filepoolable_type');
            $t->integer('filepoolable_id');
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->string('fileable_type');
            $t->integer('fileable_id');
            $t->integer('filepool_id')->nullable();
            $t->string('disk')->nullable();
            $t->string('path');
            $t->integer('user_id')->nullable();
        });
        foreach (['user_requests', 'onboarding_video_views', 'customers', 'teams'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->integer('user_id');
            });
        }
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->integer('user_id')->nullable();
        });
        Schema::create('team_user', function (Blueprint $t) {
            $t->id();
            $t->integer('team_id');
            $t->integer('user_id');
        });
        Schema::create('team_invitations', function (Blueprint $t) {
            $t->id();
            $t->integer('team_id');
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('tokenable_type');
            $t->integer('tokenable_id');
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
        });
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->nullableMorphs('subject');
            $t->nullableMorphs('causer');
        });
        Setting::setValue('coaching', 'enabled', true);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'System fixture', 'email' => 'system@example.test', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Participant fixture', 'email' => 'participant@example.test', 'role' => 'guest'],
            ['id' => 3, 'name' => 'Tutor fixture', 'email' => 'tutor@example.test', 'role' => 'tutor'],
        ]);
        DB::table('persons')->insert([
            ['id' => 2, 'user_id' => 2, 'person_id' => '1-100', 'institut_id' => 1, 'role' => 'guest'],
            ['id' => 3, 'user_id' => 3, 'person_id' => '1-200', 'institut_id' => 1, 'role' => 'tutor'],
        ]);
    }

    public function test_contract_graph_and_storage_are_removed_without_removing_accounts(): void
    {
        $this->graph();
        $result = app(TestCleanupService::class)->apply($this->payload());
        foreach (['coaching_contracts', 'coaching_plans', 'coaching_outbox', 'coaching_messages', 'coaching_assignment_history', 'coaching_notices',
            'courses', 'course_days', 'course_participant_enrollments', 'course_results', 'course_ratings', 'course_material_acknowledgements',
            'report_books', 'report_book_entries', 'admin_tasks', 'files', 'file_pools', 'messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('persons', 2);
        $this->assertDatabaseCount('users', 3);
        $this->assertSame(1, $result['counts']['coaching_contracts']);
        Storage::disk('private')->assertMissing('fixture/day.txt');
        Storage::disk('public')->assertMissing('fixture/course.txt');
        Storage::disk('private')->assertMissing('fixture/signature.png');
        (new DeliverCoachingNotices(10))->handle(app(NoticeService::class));
        Notification::assertNothingSent();
    }

    public function test_optional_exclusive_people_accounts_tokens_and_files_are_removed_and_replay_is_safe(): void
    {
        $this->graph();
        DB::table('sessions')->insert(['id' => 'fixture-session', 'user_id' => 2]);
        DB::table('user_requests')->insert(['user_id' => 2]);
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => 2]);
        DB::table('password_reset_tokens')->insert(['email' => 'participant@example.test', 'token' => 'fixture-only']);
        DB::table('users')->where('id', 2)->update(['profile_photo_path' => 'fixture/profile.png']);
        Storage::disk('public')->put('fixture/profile.png', 'fixture');
        $payload = $this->payload(true, true);
        app(TestCleanupService::class)->apply($payload);
        foreach (['persons', 'sessions', 'user_requests', 'personal_access_tokens', 'password_reset_tokens'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => 1]);
        Storage::disk('public')->assertMissing('fixture/profile.png');
        $retry = app(TestCleanupService::class)->apply($payload);
        $this->assertTrue($retry['already_missing']);
        $this->assertSame([7], $retry['missing_ids']);
        $this->assertSame(0, array_sum($retry['counts']));
    }

    public function test_shared_tutor_is_retained_while_the_selected_contract_and_exclusive_participant_are_removed(): void
    {
        $this->graph();
        $this->contract(20, 8, null);
        DB::table('coaching_contracts')->where('id', 20)->update(['uvs_person_id' => '1-999', 'participant_person_id' => null]);
        $result = app(TestCleanupService::class)->apply($this->payload(true, true));
        $this->assertDatabaseMissing('persons', ['id' => 2]);
        $this->assertDatabaseHas('persons', ['id' => 3]);
        $this->assertDatabaseHas('users', ['id' => 3]);
        $this->assertDatabaseHas('coaching_contracts', ['uvs_contract_id' => 8]);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_ordinary_course_shared_account_and_admin_are_retained(): void
    {
        $this->graph();
        DB::table('courses')->insert(['id' => 22, 'klassen_id' => 'ordinary-fixture', 'title' => 'ordinary', 'institut_id' => 1, 'type' => 'ordinary', 'primary_tutor_person_id' => 3]);
        DB::table('persons')->where('id', 2)->update(['user_id' => 1]);
        $result = app(TestCleanupService::class)->apply($this->payload(true, true));
        $this->assertDatabaseCount('persons', 2);
        $this->assertDatabaseHas('users', ['id' => 1]);
        $this->assertDatabaseHas('courses', ['id' => 22]);
        $this->assertCount(2, $result['warnings']);
    }

    public function test_wrong_contract_source_identity_and_unsafe_files_block_every_database_change(): void
    {
        $this->graph();
        $payload = $this->payload();
        $payload['contracts'][0]['beratung_id'] = 'foreign-fixture';
        try {
            app(TestCleanupService::class)->apply($payload);
            $this->fail('Foreign identity accepted');
        } catch (ConflictHttpException $e) {
        }
        $this->assertDatabaseCount('coaching_contracts', 1);
        DB::table('files')->where('id', 1)->update(['path' => '../outside.txt']);
        try {
            app(TestCleanupService::class)->apply($this->payload());
            $this->fail('Unsafe path accepted');
        } catch (ConflictHttpException $e) {
        }
        $this->assertDatabaseCount('coaching_contracts', 1);
        $this->assertDatabaseCount('files', 2);
    }

    public function test_registration_without_imported_contract_is_removed_only_for_bound_person_id(): void
    {
        $payload = $this->payload(true, false);
        $result = app(TestCleanupService::class)->apply($payload);
        $this->assertTrue($result['already_missing']);
        $this->assertDatabaseMissing('persons', ['id' => 2]);
        $this->assertDatabaseHas('persons', ['id' => 3]);
        $this->assertDatabaseCount('users', 2);
        $payload['participant_person_ids'] = ['1-200'];
        $this->expectException(ConflictHttpException::class);
        app(TestCleanupService::class)->apply($payload);
    }

    public function test_shared_storage_path_is_preserved_without_deleting_the_other_reference(): void
    {
        $this->graph();
        DB::table('files')->insert(['fileable_type' => Person::class, 'fileable_id' => 3, 'disk' => 'private', 'path' => 'fixture/day.txt']);
        $result = app(TestCleanupService::class)->apply($this->payload());
        $this->assertDatabaseCount('files', 1);
        Storage::disk('private')->assertExists('fixture/day.txt');
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_shared_account_is_retained_for_all_linked_candidates_when_one_is_ineligible(): void
    {
        $this->graph();
        DB::table('persons')->where('id', 3)->update(['user_id' => 2]);
        DB::table('courses')->insert(['id' => 22, 'klassen_id' => 'ordinary-fixture', 'title' => 'ordinary', 'institut_id' => 1, 'type' => 'ordinary', 'primary_tutor_person_id' => 3]);
        $result = app(TestCleanupService::class)->apply($this->payload(true, true));
        $this->assertDatabaseCount('persons', 2);
        $this->assertDatabaseHas('users', ['id' => 2]);
        $this->assertDatabaseMissing('coaching_contracts', ['id' => 10]);
        $this->assertCount(2, $result['warnings']);
    }

    public function test_unknown_person_and_morph_references_are_retained_without_blocking_contract_cleanup(): void
    {
        $this->graph();
        Schema::create('fixture_diagnoses', function (Blueprint $t) {
            $t->id();
            $t->integer('person_id');
        });
        Schema::create('fixture_forms', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner');
        });
        DB::table('fixture_diagnoses')->insert(['person_id' => 2]);
        DB::table('fixture_forms')->insert(['owner_type' => User::class, 'owner_id' => 3]);
        app(TestCleanupService::class)->apply($this->payload(true, true));
        $this->assertDatabaseCount('persons', 2);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('fixture_diagnoses', 1);
        $this->assertDatabaseCount('fixture_forms', 1);
        $this->assertDatabaseCount('coaching_contracts', 0);
    }

    public function test_account_uploading_a_file_at_another_object_is_retained(): void
    {
        $this->graph();
        DB::table('files')->insert(['fileable_type' => Course::class, 'fileable_id' => 99, 'disk' => 'private', 'path' => 'fixture/foreign.txt', 'user_id' => 2]);
        app(TestCleanupService::class)->apply($this->payload(true, false));
        $this->assertDatabaseHas('persons', ['id' => 2]);
        $this->assertDatabaseHas('users', ['id' => 2]);
        $this->assertDatabaseHas('files', ['fileable_id' => 99]);
        $this->assertDatabaseCount('coaching_contracts', 0);
    }

    public function test_personal_activity_and_legacy_request_attachment_are_removed(): void
    {
        $this->graph();
        Schema::table('user_requests', function (Blueprint $t) {
            $t->string('attachment_path')->nullable();
        });
        DB::table('user_requests')->insert(['user_id' => 2, 'attachment_path' => 'fixture/request.txt']);
        DB::table('activity_log')->insert(['subject_type' => Person::class, 'subject_id' => 2, 'causer_type' => User::class, 'causer_id' => 1]);
        Storage::disk('private')->put('fixture/request.txt', 'fixture');
        app(TestCleanupService::class)->apply($this->payload(true, false));
        $this->assertDatabaseCount('activity_log', 0);
        $this->assertDatabaseCount('user_requests', 0);
        Storage::disk('private')->assertMissing('fixture/request.txt');
    }

    public function test_a_database_failure_rolls_back_the_graph_before_any_physical_file_deletion(): void
    {
        $this->graph();
        DB::statement("CREATE TRIGGER fixture_abort BEFORE DELETE ON users WHEN old.id = 2 BEGIN SELECT RAISE(ABORT, 'fixture rollback'); END");
        try {
            app(TestCleanupService::class)->apply($this->payload(true, false));
            $this->fail('The fixture SQL failure was hidden');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('fixture rollback', $error->getMessage());
        }
        $this->assertDatabaseCount('coaching_contracts', 1);
        $this->assertDatabaseCount('coaching_plans', 1);
        $this->assertDatabaseCount('files', 2);
        $this->assertDatabaseHas('persons', ['id' => 2]);
        Storage::disk('private')->assertExists('fixture/day.txt');
    }

    public function test_normalized_surviving_file_reference_preserves_shared_storage(): void
    {
        $this->graph();
        DB::table('files')->insert(['fileable_type' => Person::class, 'fileable_id' => 3, 'disk' => 'private', 'path' => 'fixture/./day.txt']);
        app(TestCleanupService::class)->apply($this->payload());
        Storage::disk('private')->assertExists('fixture/day.txt');
        $this->assertDatabaseCount('files', 1);
    }

    public function test_selected_noncanonical_path_is_rejected_before_database_changes(): void
    {
        $this->graph();
        DB::table('files')->where('id', 1)->update(['path' => 'fixture/./day.txt']);
        try {
            app(TestCleanupService::class)->apply($this->payload());
            $this->fail('Noncanonical storage path accepted');
        } catch (ConflictHttpException $error) {
            $this->assertDatabaseCount('coaching_contracts', 1);
            $this->assertDatabaseCount('files', 2);
        }
    }

    public function test_missing_cleanup_extension_preserves_ordinary_sync_for_404_and_405(): void
    {
        $this->graph();
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 0])->twice()
            ->andReturn(['ok' => false, 'status' => 404], ['ok' => false, 'status' => 405]);
        $api->shouldReceive('request')->with('GET', '/api/coaching/contracts', [], ['after_id' => 0])->twice()
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => null]]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->import());
        $this->assertSame(0, app(SyncService::class)->import());
        $this->assertDatabaseCount('coaching_contracts', 1);
    }

    public function test_cleanup_feed_server_failure_remains_visible_and_releases_the_sync_lock(): void
    {
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 0])
            ->andReturn(['ok' => false, 'status' => 500]);
        $this->app->instance(ApiUvsService::class, $api);
        try {
            app(SyncService::class)->import();
            $this->fail('Feed server failure was hidden');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('nicht gelesen', $error->getMessage());
        }
        $lock = Cache::lock('coaching:sync', 1);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_cleanup_pagination_follows_an_empty_page_before_normal_import(): void
    {
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->ordered()->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 0])
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => 200]]);
        $api->shouldReceive('request')->once()->ordered()->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 200])
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => null]]);
        $api->shouldReceive('request')->once()->ordered()->with('GET', '/api/coaching/contracts', [], ['after_id' => 0])
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => null]]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->import());
    }

    public function test_foreign_report_book_referencing_a_selected_day_blocks_the_entire_batch(): void
    {
        $this->graph();
        DB::table('report_books')->insert(['id' => 99, 'user_id' => 3]);
        DB::table('report_book_entries')->insert(['report_book_id' => 99, 'course_day_id' => 12]);
        try {
            app(TestCleanupService::class)->apply($this->payload());
            $this->fail('A foreign report book was altered');
        } catch (ConflictHttpException $error) {
            $this->assertDatabaseCount('coaching_contracts', 1);
            $this->assertDatabaseHas('report_book_entries', ['report_book_id' => 99, 'course_day_id' => 12]);
            Storage::disk('private')->assertExists('fixture/day.txt');
        }
    }

    public function test_notice_history_at_another_contract_retains_the_person_and_account(): void
    {
        $this->graph();
        $this->contract(20, 8, null);
        DB::table('coaching_contracts')->where('id', 20)->update(['uvs_person_id' => '1-999', 'participant_person_id' => null]);
        DB::table('messages')->insert(['id' => 99, 'from_user' => 1, 'to_user' => 2]);
        DB::table('coaching_notices')->insert(['coaching_contract_id' => 20, 'event_key' => str_repeat('d', 64), 'kind' => 'fixture',
            'recipient_role' => 'participant', 'uvs_person_id' => '1-100', 'content' => '{}', 'message_id' => 99]);
        app(TestCleanupService::class)->apply($this->payload(true, false));
        $this->assertDatabaseHas('persons', ['id' => 2]);
        $this->assertDatabaseHas('users', ['id' => 2]);
        $this->assertDatabaseHas('messages', ['id' => 99]);
        $this->assertDatabaseHas('coaching_notices', ['message_id' => 99]);
        $this->assertDatabaseMissing('coaching_contracts', ['id' => 10]);
    }

    public function test_direct_import_does_not_overtake_an_existing_sync_lock(): void
    {
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldNotReceive('request');
        $this->app->instance(ApiUvsService::class, $api);
        $lock = Cache::lock('coaching:sync', 600);
        $this->assertTrue($lock->get());
        try {
            app(SyncService::class)->import();
            $this->fail('A concurrent sync was allowed');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('bereits', $error->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_existing_sync_reads_cleanup_before_contracts_and_acknowledges_after_commit(): void
    {
        $this->graph();
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->once()->ordered()->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 0])
            ->andReturn(['ok' => true, 'data' => ['data' => [$this->request()], 'next_after_id' => null]]);
        $api->shouldReceive('request')->once()->ordered()->withArgs(function ($method, $path, $body) {
            $this->assertDatabaseCount('coaching_contracts', 0);
            $this->assertSame(0, DB::transactionLevel());

            return $method === 'POST' && $path === '/api/coaching/cleanup-requests/4/ack' && $body['request_id'] === 'fixture-request' && $body['result']['counts']['coaching_contracts'] === 1;
        })->andReturn(['ok' => true]);
        $api->shouldReceive('request')->once()->ordered()->with('GET', '/api/coaching/contracts', [], ['after_id' => 0])
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => null]]);
        $this->app->instance(ApiUvsService::class, $api);
        $this->assertSame(0, app(SyncService::class)->import());
    }

    public function test_ack_failure_leaves_a_safe_idempotent_retry_and_releases_the_sync_lock(): void
    {
        $this->graph();
        $api = \Mockery::mock(ApiUvsService::class);
        $api->shouldReceive('request')->with('GET', '/api/coaching/cleanup-requests', [], ['after_id' => 0])->twice()
            ->andReturn(['ok' => true, 'data' => ['data' => [$this->request()], 'next_after_id' => null]]);
        $api->shouldReceive('request')->withArgs(fn ($method, $path) => $method === 'POST' && $path === '/api/coaching/cleanup-requests/4/ack')->twice()
            ->andReturn(['ok' => false, 'status' => 503], ['ok' => true]);
        $api->shouldReceive('request')->with('GET', '/api/coaching/contracts', [], ['after_id' => 0])->once()
            ->andReturn(['ok' => true, 'data' => ['data' => [], 'next_after_id' => null]]);
        $this->app->instance(ApiUvsService::class, $api);
        try {
            app(SyncService::class)->import();
            $this->fail('ACK failure hidden');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nicht bestätigt', $e->getMessage());
        }
        $this->assertDatabaseCount('coaching_contracts', 0);
        $this->assertSame(0, app(SyncService::class)->import());
        $lock = Cache::lock('coaching:sync', 1);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    private function graph(): void
    {
        $this->contract(10, 7, 11);
        DB::table('courses')->insert(['id' => 11, 'klassen_id' => 'ec-fixture-10', 'institut_id' => 1, 'title' => 'Test coaching', 'type' => 'coaching',
            'primary_tutor_person_id' => 3, 'settings' => json_encode(['coaching_contract_id' => 10])]);
        DB::table('course_days')->insert(['id' => 12, 'course_id' => 11, 'date' => '2026-10-05']);
        DB::table('course_participant_enrollments')->insert(['id' => 13, 'course_id' => 11, 'person_id' => 2]);
        DB::table('course_results')->insert(['course_id' => 11, 'person_id' => 2]);
        DB::table('course_ratings')->insert(['course_id' => 11, 'user_id' => 2, 'participant_id' => 2]);
        DB::table('course_material_acknowledgements')->insert(['course_id' => 11, 'person_id' => 2, 'signature_path' => 'fixture/signature.png']);
        DB::table('report_books')->insert(['id' => 14, 'course_id' => 11, 'user_id' => 2]);
        DB::table('report_book_entries')->insert(['id' => 15, 'report_book_id' => 14]);
        DB::table('admin_tasks')->insert(['context_type' => \App\Models\ReportBook::class, 'context_id' => 14]);
        DB::table('coaching_plans')->insert(['coaching_contract_id' => 10, 'revision' => 1, 'contract_version' => str_repeat('a', 64),
            'tutor_person_id' => 3, 'participant_person_id' => 2, 'created_by' => 2, 'items' => '[]']);
        DB::table('coaching_messages')->insert(['coaching_contract_id' => 10, 'user_id' => 2, 'plan_revision' => 1, 'body' => 'fixture']);
        DB::table('coaching_assignment_history')->insert(['coaching_contract_id' => 10, 'tutor_person_id' => 3, 'assigned_by' => 1]);
        DB::table('coaching_outbox')->insert(['event_id' => 'fixture-event', 'coaching_contract_id' => 10, 'type' => 'plan', 'payload' => '{}']);
        DB::table('messages')->insert(['id' => 16, 'from_user' => 1, 'to_user' => 2]);
        DB::table('coaching_notices')->insert(['coaching_contract_id' => 10, 'event_key' => str_repeat('b', 64), 'kind' => 'fixture',
            'recipient_role' => 'participant', 'uvs_person_id' => '1-100', 'content' => '{}', 'message_id' => 16]);
        DB::table('file_pools')->insert(['id' => 17, 'filepoolable_type' => Course::class, 'filepoolable_id' => 11]);
        DB::table('files')->insert([
            ['id' => 1, 'fileable_type' => CourseDay::class, 'fileable_id' => 12, 'filepool_id' => null, 'disk' => 'private', 'path' => 'fixture/day.txt'],
            ['id' => 2, 'fileable_type' => FilePool::class, 'fileable_id' => 17, 'filepool_id' => 17, 'disk' => 'public', 'path' => 'fixture/course.txt'],
        ]);
        Storage::disk('private')->put('fixture/day.txt', 'fixture');
        Storage::disk('public')->put('fixture/course.txt', 'fixture');
        Storage::disk('private')->put('fixture/signature.png', 'fixture');
    }

    private function contract(int $id, int $uvsId, ?int $courseId): void
    {
        DB::table('coaching_contracts')->insert(['id' => $id, 'uuid' => 'fixture-'.$id, 'uvs_contract_id' => $uvsId, 'institut_id' => 1,
            'uvs_person_id' => '1-100', 'beratung_id' => 'fixture-consultation', 'uvs_tutor_person_id' => '1-200',
            'participant_person_id' => 2, 'tutor_person_id' => 3, 'course_id' => $courseId, 'title' => 'Test coaching',
            'agreed_minutes' => 60, 'unit_minutes' => 45, 'contract_version' => str_repeat('a', 64)]);
    }

    private function payload(bool $participant = false, bool $tutors = false): array
    {
        return ['contract_ids' => [7], 'contracts' => [['id' => 7, 'institut_id' => 1, 'person_id' => '1-100', 'beratung_id' => 'fixture-consultation']],
            'delete_participant' => $participant, 'delete_tutors' => $tutors,
            'participant_person_ids' => $participant ? ['1-100'] : [], 'tutor_person_ids' => $tutors ? ['1-200'] : []];
    }

    private function request(): array
    {
        return ['id' => 4, 'request_id' => 'fixture-request', 'payload_hash' => str_repeat('c', 64), 'payload' => $this->payload()];
    }
}
