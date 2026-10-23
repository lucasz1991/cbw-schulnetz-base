<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\ReportBooks\StampStorage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;

class ReportBookStampStorageTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, fn ($app) => $app['config']->set([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]));
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Setting::setValue('api', 'base_api_key', 'test-stamp-key');
        Storage::fake('private'); Storage::fake('public');
    }

    public function test_upload_and_verification_preserve_exact_private_bytes_and_immutable_path(): void
    {
        $file = UploadedFile::fake()->image('cbw.png', 30, 20);
        $originalHash = hash_file('sha256', $file->getRealPath());
        $response = $this->withHeader('X-API-KEY', 'test-stamp-key')
            ->postJson('/api/admin/reportbook-stamps', ['file' => $file])->assertOk()->assertJson(['success' => true]);
        $stamp = $response->json();
        $this->assertSame($originalHash, $stamp['stamp_sha256']);
        $this->assertTrue(StampStorage::verify($stamp['stamp_path'], $stamp['stamp_sha256'], $stamp['stamp_mime']));
        Storage::disk('public')->assertMissing($stamp['stamp_path']);
        $this->postJson('/api/admin/reportbook-stamps/verify', ['path' => $stamp['stamp_path'], 'hash' => $stamp['stamp_sha256'], 'mime' => $stamp['stamp_mime'], 'preview' => true])
            ->assertOk()->assertJson(['verified' => true]);
        $this->deleteJson('/api/admin/delete', ['path' => 'ordinary/../'.$stamp['stamp_path'], 'visibility' => 'private'])->assertForbidden();
        Storage::disk('private')->assertExists($stamp['stamp_path']);
    }

    public function test_invalid_content_size_and_missing_api_key_are_rejected(): void
    {
        $this->postJson('/api/admin/reportbook-stamps', ['file' => UploadedFile::fake()->image('stamp.png')])->assertForbidden();
        $this->withHeader('X-API-KEY', 'test-stamp-key')->postJson('/api/admin/reportbook-stamps', [
            'file' => UploadedFile::fake()->createWithContent('stamp.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertUnprocessable();
        $this->postJson('/api/admin/reportbook-stamps', ['file' => UploadedFile::fake()->image('stamp.png')->size(4097)])->assertUnprocessable();
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_hash_mime_and_path_tampering_fail_verification(): void
    {
        $stamp = StampStorage::store(UploadedFile::fake()->image('stamp.jpg', 30, 20));
        $this->assertFalse(StampStorage::verify('../'.$stamp['stamp_path'], $stamp['stamp_sha256'], $stamp['stamp_mime']));
        $this->assertFalse(StampStorage::verify($stamp['stamp_path'], str_repeat('0', 64), $stamp['stamp_mime']));
        $this->assertFalse(StampStorage::verify($stamp['stamp_path'], $stamp['stamp_sha256'], 'image/png'));
        Storage::disk('private')->put($stamp['stamp_path'], 'changed');
        $this->assertFalse(StampStorage::verify($stamp['stamp_path'], $stamp['stamp_sha256'], $stamp['stamp_mime']));
        $this->assertNull(StampStorage::preview($stamp['stamp_path'], $stamp['stamp_sha256'], $stamp['stamp_mime']));
    }

    public function test_actual_storage_api_allows_only_unreferenced_new_signature_upload_cleanup(): void
    {
        Schema::create('report_book_entries', function (Blueprint $t) { $t->id(); $t->integer('report_book_id'); $t->integer('status'); });
        Schema::create('files', function (Blueprint $t) { $t->id(); $t->morphs('fileable'); $t->string('type'); $t->string('path'); });
        (require database_path('migrations/2026_10_19_180000_create_report_book_location_policies.php'))->up();
        $this->withHeader('X-API-KEY', 'test-stamp-key');
        $upload = fn () => $this->postJson('/api/admin/upload', ['file' => UploadedFile::fake()->image('trainer.png'),
            'folder' => 'signatures/report-book-uploads/1', 'visibility' => 'private'])->assertOk()->json('path');
        $orphanPath = $upload();
        $this->assertStringStartsWith('signatures/report-book-uploads/1/', $orphanPath);
        $this->deleteJson('/api/admin/delete', ['path' => $orphanPath, 'visibility' => 'private'])->assertOk();
        Storage::disk('private')->assertMissing($orphanPath);

        $referencedPath = $upload();
        DB::table('report_book_entries')->insert(['report_book_id' => 1, 'status' => 1]);
        DB::table('files')->insert(['fileable_type' => \App\Models\ReportBook::class, 'fileable_id' => 1,
            'type' => 'sign_reportbook_trainer', 'path' => $referencedPath]);
        $this->deleteJson('/api/admin/delete', ['path' => $referencedPath, 'visibility' => 'private'])->assertStatus(409);
        Storage::disk('private')->assertExists($referencedPath);
        $this->assertSame(1, DB::table('files')->count());

        DB::table('report_book_policy_audit')->insert(['event_key' => 'test-signature-history', 'event' => 'manual_signatures_preserved',
            'payload' => json_encode(['signature_files' => [['path' => $referencedPath]]]), 'created_at' => now()]);
        DB::table('files')->delete();
        $this->deleteJson('/api/admin/delete', ['path' => $referencedPath, 'visibility' => 'private'])->assertStatus(409);
        Storage::disk('private')->assertExists($referencedPath);
    }
}
