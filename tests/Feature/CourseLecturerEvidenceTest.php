<?php

namespace Tests\Feature;

use App\Livewire\Tools\FilePools\ManageFilePools;
use App\Livewire\Tutor\Courses\CourseDocumentationPanel;
use App\Livewire\Tutor\Courses\ManageCourseInvoice;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\File;
use App\Models\User;
use App\Services\Courses\CourseLecturerAccess;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CourseLecturerEvidenceTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-11-03 10:00:00', 'Europe/Berlin'));
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('type')->default('regular');
            $t->string('klassen_id')->nullable();
            $t->string('title')->nullable();
            $t->unsignedBigInteger('primary_tutor_person_id')->nullable();
            $t->json('lecturer_plan')->nullable();
            $t->unsignedInteger('lecturer_plan_revision')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('vorname')->nullable();
            $t->string('nachname')->nullable();
            $t->softDeletes();
        });
        Schema::create('course_days', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id');
            $t->date('date');
            $t->string('start_time')->nullable();
            $t->string('end_time')->nullable();
            $t->text('notes')->nullable();
            $t->integer('note_status')->default(0);
            $t->unsignedBigInteger('notes_author_user_id')->nullable();
            $t->string('notes_assignment_id')->nullable();
            $t->unsignedInteger('notes_revision')->nullable();
            $t->json('attendance_data')->nullable();
            $t->json('day_sessions')->nullable();
            $t->json('settings')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('course_participant_enrollments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id');
            $t->unsignedBigInteger('person_id');
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id();
            $t->string('fileable_type');
            $t->unsignedBigInteger('fileable_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name')->nullable();
            $t->string('path');
            $t->string('disk')->nullable();
            $t->string('type');
            $t->string('mime_type')->nullable();
            $t->unsignedInteger('document_revision')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unsignedBigInteger('size')->nullable();
            $t->unsignedBigInteger('lecturer_person_id')->nullable();
            $t->string('lecturer_assignment_id')->nullable();
            $t->string('lecturer_contract_id')->nullable();
            $t->unsignedInteger('lecturer_plan_revision')->nullable();
            $t->unsignedBigInteger('supersedes_file_id')->nullable();
        });
        Schema::create('file_pools', function (Blueprint $t) {
            $t->id();
            $t->string('filepoolable_type');
            $t->unsignedBigInteger('filepoolable_id');
            $t->string('title');
            $t->string('type')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });
        DB::table('persons')->insert([
            ['id' => 101, 'user_id' => 11, 'vorname' => 'Alter', 'nachname' => 'Dozent'],
            ['id' => 102, 'user_id' => 12, 'vorname' => 'Neuer', 'nachname' => 'Dozent'],
            ['id' => 201, 'user_id' => 21, 'vorname' => 'Test', 'nachname' => 'Teilnehmer'],
        ]);
        Storage::fake('private');
        Storage::fake('public');
        \Illuminate\Support\Facades\Bus::fake();
        Gate::define('invoices.view', fn () => false);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function signIn(int $id): void
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'name' => 'Test '.$id, 'role' => 'tutor']);
        $this->actingAs($user);
    }

    private function course(): Course
    {
        return Course::withoutEvents(fn () => Course::query()->create([
            'type' => 'regular', 'klassen_id' => '26T01-1-BMFK', 'primary_tutor_person_id' => 101,
            'lecturer_plan_revision' => 7, 'lecturer_plan' => [
                'status' => 'ready', 'person_ids' => [101, 102], 'assignments' => [
                    ['assignment_id' => 'first', 'person_id' => 101, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-31'],
                    ['assignment_id' => 'second', 'person_id' => 102, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-06'],
                ],
            ],
        ]));
    }

    private function day(Course $course, array $attributes = []): CourseDay
    {
        return CourseDay::withoutEvents(function () use ($course, $attributes) {
            $day = new CourseDay;
            $day->forceFill(array_merge([
                'course_id' => $course->id, 'date' => '2026-11-03', 'start_time' => '08:00', 'end_time' => '16:00',
                'notes' => '', 'note_status' => 0, 'attendance_data' => ['participants' => []],
            ], $attributes))->save();

            return $day;
        });
    }

    private function file(Course|CourseDay $subject, string $type, int $owner): File
    {
        $path = 'evidence/'.$type.'-'.$owner.'.pdf';
        Storage::disk('private')->put($path, 'synthetic original '.$owner);

        return File::withoutEvents(fn () => $subject->files()->create([
            'user_id' => $owner, 'type' => $type, 'name' => 'Original.pdf', 'path' => $path, 'disk' => 'private',
            'mime_type' => 'application/pdf', 'document_revision' => 1,
        ]));
    }

    private function assertStatus(int $status, callable $work): void
    {
        try {
            $work();
            $this->fail('The protected action unexpectedly succeeded.');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    private function saveUiPreview(string $name, string $html): void
    {
        $directory = getenv('CBW_LECTURER_UI_DIR');
        if (! $directory) {
            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = '';
        foreach ($manifest as $asset) {
            $paths = array_merge(str_ends_with($asset['file'], '.css') ? [$asset['file']] : [], $asset['css'] ?? []);
            foreach ($paths as $path) {
                $css .= file_get_contents(public_path('build/'.$path));
            }
        }
        $document = '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Schulnetz Originalansicht – lokale Beispieldaten</title><style>'.$css.'</style><style>body{display:block!important;width:100%!important;min-width:0!important;box-sizing:border-box!important;margin:0!important;background:#f8fafc;padding:32px!important;font-family:Arial,sans-serif!important}main{max-width:1160px;margin:auto;background:white;padding:24px;border:1px solid #e2e8f0;border-radius:12px}[x-cloak],[x-show="showSelectDayCalendar"]{display:none!important}</style><main style="display:block!important;width:calc(100vw - 64px)!important;max-width:1100px!important;min-width:0!important;box-sizing:border-box!important;margin:0 auto!important;padding:24px!important;font-family:Arial,sans-serif!important"><p style="font-size:12px;color:#64748b;margin-bottom:20px">Lokale Original-Blades · synthetische Daten · keine LIVE-Abnahme</p>'.$html.'</main></html>';
        file_put_contents($directory.'/'.$name.'.html', $document);
    }

    public function test_former_lecturer_cannot_write_even_to_own_previous_day(): void
    {
        $course = $this->course();
        $old = $this->day($course, ['date' => '2026-10-21']);
        $this->signIn(11);
        $this->assertTrue(CourseLecturerAccess::canView(auth()->user(), $course));
        $this->assertStatus(403, fn () => $old->update(['notes' => 'Unauthorized edit']));
        $this->assertSame('', $old->fresh()->notes);
    }

    public function test_successor_cannot_edit_previous_day_or_foreign_authorship(): void
    {
        $course = $this->course();
        $old = $this->day($course, ['date' => '2026-10-21', 'notes' => 'Old original', 'notes_author_user_id' => 11]);
        $foreign = $this->day($course, ['notes' => 'Imported foreign document', 'notes_author_user_id' => 11]);
        $this->signIn(12);
        $this->assertStatus(403, fn () => $old->update(['notes' => 'Changed']));
        $this->assertStatus(403, fn () => $foreign->update(['notes' => 'Changed']));
        $this->assertSame('Old original', $old->fresh()->notes);
        $this->assertSame('Imported foreign document', $foreign->fresh()->notes);
    }

    public function test_confirmed_document_and_signature_cannot_be_changed_or_deleted(): void
    {
        $day = $this->day($this->course(), ['notes' => 'Signed original', 'note_status' => 2, 'notes_author_user_id' => 12, 'notes_revision' => 1]);
        $signature = $this->file($day, 'sign_courseday_doku_tutor', 12);
        $this->signIn(12);
        $this->assertStatus(409, fn () => $day->update(['notes' => 'Changed']));
        $this->assertStatus(409, fn () => $signature->delete());
        $this->assertSame('Signed original', $day->fresh()->notes);
        $this->assertNotNull($signature->fresh());
        Storage::disk('private')->assertExists($signature->path);
    }

    public function test_current_day_notes_and_attendance_receive_server_actor_and_assignment(): void
    {
        $course = $this->course();
        $day = $this->day($course);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course->id, 'person_id' => 201, 'is_active' => true]);
        $this->signIn(12);
        $day->update(['notes' => 'My documentation', 'attendance_data' => ['participants' => [
            201 => ['present' => true, 'updated_by' => 11, 'lecturer_assignment_id' => 'forged', 'lecturer_plan_revision' => 1],
        ]]]);
        $saved = $day->fresh();
        $this->assertSame(12, (int) $saved->notes_author_user_id);
        $this->assertSame('second', $saved->notes_assignment_id);
        $this->assertSame(1, (int) $saved->notes_revision);
        $row = $saved->attendance_data['participants'][201];
        $this->assertSame(12, $row['updated_by']);
        $this->assertSame('second', $row['lecturer_assignment_id']);
        $this->assertSame(7, $row['lecturer_plan_revision']);
    }

    public function test_system_attendance_pull_can_update_previous_day_without_forging_a_human_actor(): void
    {
        $day = $this->day($this->course(), ['date' => '2026-10-21']);
        auth()->forgetGuards();
        CourseLecturerAccess::runAsSystem(fn () => $day->update(['attendance_data' => ['participants' => [201 => ['present' => true, 'src_api_id' => 'UVS-fixture']]]]));
        $row = $day->fresh()->attendance_data['participants'][201];
        $this->assertSame('UVS-fixture', $row['src_api_id']);
        $this->assertArrayNotHasKey('updated_by', $row);
        $this->assertFalse(CourseLecturerAccess::isSystemSync());
    }

    public function test_private_invoice_read_remains_owner_bound_and_original_cannot_be_deleted(): void
    {
        $invoice = $this->file($this->course(), 'invoice', 11);
        $this->signIn(12);
        $this->assertStatus(403, fn () => $invoice->download());
        $this->assertStatus(403, fn () => $invoice->getEphemeralPublicUrl());
        $this->signIn(11);
        $response = $invoice->download();
        ob_start();
        try {
            $response->sendContent();
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame('synthetic original 11', $contents);
        $this->assertStatus(409, fn () => $invoice->delete());
        Storage::disk('private')->assertExists($invoice->path);
    }

    public function test_former_lecturer_file_pool_without_existing_folder_renders_empty_without_writes(): void
    {
        $course = $this->course();
        $this->signIn(11);
        $component = Livewire::test(ManageFilePools::class, ['modelType' => Course::class, 'modelId' => $course->id, 'readOnly' => false])
            ->assertSet('readOnly', true)->assertSet('filePoolId', null)
            ->assertSee('Keine Dateien vorhanden.')->assertDontSee('Hinzufügen');
        $this->saveUiPreview('base-dateien-ehemaliger-dozent', $component->html());
        $this->assertSame(0, DB::table('file_pools')->count());
        $this->assertSame(0, File::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_invoice_assignment_picker_separates_returning_lecturer_and_keeps_all_uploaded_versions(): void
    {
        $course = $this->course();
        $plan = $course->lecturer_plan;
        $plan['assignments'][1]['ends_on'] = '2026-11-03';
        $plan['assignments'][] = ['assignment_id' => 'return', 'person_id' => 101, 'contract_id' => 'LV-A2', 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-06'];
        $course->forceFill(['lecturer_plan' => $plan])->save();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00', 'Europe/Berlin'));
        foreach (['2026-10-21', '2026-11-02', '2026-11-05'] as $date) {
            $this->day($course, ['date' => $date, 'note_status' => 2]);
        }
        $this->signIn(11);
        $component = Livewire::test(ManageCourseInvoice::class, ['course' => $course])
            ->assertSet('invoiceAssignmentId', 'return')->assertSee('Eigener Einsatz für diese Rechnung');
        $this->saveUiPreview('base-rechnung-eigene-einsaetze-a-b-a', $component->html());
        $component->set('invoiceAssignmentId', 'first')
            ->set('invoiceUpload', UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\nSynthetic first\n%%EOF"))
            ->call('uploadInvoice')->assertHasNoErrors();
        $first = File::query()->sole();
        $this->assertSame('first', $first->lecturer_assignment_id);
        $this->assertSame(11, (int) $first->user_id);
        Storage::disk('private')->assertExists($first->path);
        $component->set('invoiceUpload', UploadedFile::fake()->createWithContent('first-v2.pdf', "%PDF-1.4\nSynthetic replacement\n%%EOF"))
            ->call('uploadInvoice')->assertHasNoErrors();
        $replacement = File::query()->latest('id')->first();
        $this->assertSame($first->id, (int) $replacement->supersedes_file_id);
        $this->assertSame(2, (int) $replacement->document_revision);
        Storage::disk('private')->assertExists($first->path);
        $component->set('invoiceAssignmentId', 'return')
            ->set('invoiceUpload', UploadedFile::fake()->createWithContent('return.pdf', "%PDF-1.4\nSynthetic return\n%%EOF"))
            ->call('uploadInvoice')->assertHasNoErrors();
        $return = File::query()->latest('id')->first();
        $this->assertSame('return', $return->lecturer_assignment_id);
        $this->assertNull($return->supersedes_file_id);
        $this->signIn(12);
        Livewire::test(ManageCourseInvoice::class, ['course' => $course])
            ->assertSet('invoiceAssignmentId', 'second')->assertDontSee('Eigener Einsatz für diese Rechnung')
            ->set('invoiceUpload', UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\nSynthetic other owner\n%%EOF"))
            ->call('uploadInvoice')->assertHasNoErrors();
        $this->assertSame(4, File::query()->count());
        $this->assertSame(12, (int) File::query()->latest('id')->first()->user_id);
        Storage::disk('private')->assertExists($first->path);
        Storage::disk('private')->assertExists($replacement->path);
        Storage::disk('private')->assertExists($return->path);
    }

    public function test_former_lecturer_documentation_panel_renders_existing_text_without_editor_or_actions(): void
    {
        $course = $this->course();
        $course->forceFill(['title' => 'DLO1 · BMFK'])->save();
        $this->day($course, ['date' => '2026-10-21', 'notes' => '<p>Unterricht am 21.10.: bestehende Kursdokumentation des früheren Hauptdozenten.</p>', 'note_status' => 1, 'notes_author_user_id' => 11]);
        $this->signIn(11);
        $component = Livewire::test(CourseDocumentationPanel::class, ['courseId' => $course->id])
            ->assertSee('bestehende Kursdokumentation')->assertDontSee('wire:click="saveNotes"', false)
            ->assertDontSee('wire:click="finalizeDay"', false)->assertDontSee('toastui-editor', false);
        $history = Blade::render('<x-courses.lecturer-history :course="$course" :show-own-role="true" />', compact('course'));
        $this->saveUiPreview('base-dokumentation-ehemaliger-dozent', $history.$component->html());
        $this->assertSame(1, CourseDay::query()->count());
    }

    public function test_forged_foreign_invoice_assignment_is_rejected_before_file_io(): void
    {
        $course = $this->course();
        $this->signIn(11);
        $component = new ManageCourseInvoice;
        $component->mount($course);
        $component->invoiceAssignmentId = 'second';
        $component->invoiceUpload = UploadedFile::fake()->createWithContent('forged.pdf', "%PDF-1.4\nMust never store\n%%EOF");
        $this->assertStatus(403, fn () => $component->uploadInvoice());
        $this->assertSame(0, File::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }
}
