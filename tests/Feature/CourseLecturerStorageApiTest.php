<?php

namespace Tests\Feature;

use App\Jobs\DeleteTempFile;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\File;
use App\Models\Setting;
use App\Services\Courses\CourseLecturerAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseLecturerStorageApiTest extends TestCase
{
    private const API_KEY = 'isolated-storage-api-key';

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'session.driver' => 'array',
                'activitylog.dispatch_enabled' => false]);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake();
        Storage::fake('private');
        Storage::fake('public');
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('key');
            $t->text('value');
            $t->timestamps();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->integer('course_id');
            $t->date('date');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->morphs('fileable');
            $t->integer('user_id')->nullable();
            foreach (['name', 'type', 'path', 'disk', 'mime_type'] as $field) {
                $t->string($field)->nullable();
            }$t->integer('size')->nullable();
            $t->timestamps();
        });
        Setting::setValue('api', 'base_api_key', self::API_KEY);
        $this->assertSame(self::API_KEY, Setting::where('key', 'base_api_key')->value('value'));
    }

    private function file(string $type, bool $temporal = true): File
    {
        $course = Course::withoutEvents(fn () => Course::create(['title' => 'Storage isolation', 'klassen_id' => 'storage-'.uniqid(),
            'lecturer_plan' => $temporal ? ['status' => 'ready', 'person_ids' => [101, 102], 'assignments' => [
                ['assignment_id' => 'A', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31'],
                ['assignment_id' => 'B', 'person_id' => 102, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-06'],
            ]] : null]));
        $subject = $course;
        if (str_starts_with($type, 'sign_')) {
            $id = DB::table('course_days')->insertGetId(['course_id' => $course->id, 'date' => '2026-11-03']);
            $subject = CourseDay::find($id);
        }
        $path = 'evidence/'.$course->id.'/'.$type.'.pdf';
        Storage::disk('private')->put($path, 'Synthetic preserved '.$type);

        return File::withoutEvents(fn () => $subject->files()->create(['user_id' => 123, 'type' => $type, 'name' => 'Original.pdf', 'path' => $path,
            'disk' => 'private', 'mime_type' => 'application/pdf', 'size' => 32]));
    }

    public function test_valid_server_key_resolves_invoice_and_signature_without_login_and_keeps_originals(): void
    {
        $this->assertNull(auth()->user());
        foreach (['invoice', 'sign_courseday_doku_tutor'] as $type) {
            $file = $this->file($type);
            $response = $this->postJson('/api/admin/resolve-file-url', ['file_id' => $file->id], ['X-API-KEY' => self::API_KEY])
                ->assertOk()->assertJsonPath('success', true)->assertJsonPath('strategy', 'model-ephemeral-copy');
            $this->assertStringContainsString('/temp/', $response->json('url'));
            Storage::disk('private')->assertExists($file->path);
            $this->assertNotNull($file->fresh());
            $this->assertFalse(CourseLecturerAccess::isSystemSync());
        }
        $this->assertCount(2, Storage::disk('public')->allFiles('temp'));
        Queue::assertPushed(DeleteTempFile::class, 2);
        Http::assertNothingSent();
    }

    public function test_wrong_server_key_is_rejected_before_file_query_or_copy(): void
    {
        $file = $this->file('invoice');
        $fileQueries = [];
        DB::listen(function ($event) use (&$fileQueries) {
            if (str_contains(strtolower($event->sql), 'files')) {
                $fileQueries[] = $event->sql;
            }
        });
        $this->postJson('/api/admin/resolve-file-url', ['file_id' => $file->id], ['X-API-KEY' => 'wrong-key'])->assertForbidden();
        $this->deleteJson('/api/admin/delete', ['path' => $file->path], ['X-API-KEY' => 'wrong-key'])->assertForbidden();
        $this->assertSame([], $fileQueries);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('private')->assertExists($file->path);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_path_delete_cannot_remove_temporal_invoice_or_signature(): void
    {
        foreach (['invoice', 'sign_courseday_doku_tutor'] as $type) {
            $file = $this->file($type);
            $this->deleteJson('/api/admin/delete', ['path' => $file->path, 'visibility' => 'private'], ['X-API-KEY' => self::API_KEY])->assertStatus(409);
            Storage::disk('private')->assertExists($file->path);
            $this->assertNotNull($file->fresh());
        }
        Queue::assertNothingPushed();
    }

    public function test_normalized_alias_cannot_bypass_evidence_delete_protection(): void
    {
        foreach (['invoice', 'sign_courseday_doku_tutor'] as $type) {
            $file = $this->file($type);
            $alias = dirname($file->path).'/alias/../'.basename($file->path);
            $this->assertTrue(Storage::disk('private')->exists($alias));
            $this->deleteJson('/api/admin/delete', ['path' => $alias, 'visibility' => 'private'], ['X-API-KEY' => self::API_KEY])->assertStatus(409);
            Storage::disk('private')->assertExists($file->path);
            $this->assertNotNull($file->fresh());
        }
    }

    public function test_legacy_path_deletion_and_absolute_url_resolution_keep_existing_contract(): void
    {
        $file = $this->file('invoice', false);
        $this->postJson('/api/admin/resolve-file-url', ['url' => 'https://example.test/original.pdf'], ['X-API-KEY' => self::API_KEY])
            ->assertOk()->assertJsonPath('strategy', 'pass-through')->assertJsonPath('url', 'https://example.test/original.pdf');
        $this->deleteJson('/api/admin/delete', ['path' => $file->path, 'visibility' => 'private'], ['X-API-KEY' => self::API_KEY])
            ->assertOk()->assertJsonPath('success', true);
        Storage::disk('private')->assertMissing($file->path);
        // The existing endpoint deletes storage only; it does not delete the DB row.
        $this->assertNotNull($file->fresh());
        Http::assertNothingSent();
    }
}
