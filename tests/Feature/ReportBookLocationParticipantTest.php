<?php

namespace Tests\Feature;

use App\Livewire\Tools\Ai\ReportBookAiAssistant;
use App\Livewire\Tools\Signatures\SignatureForm;
use App\Livewire\User\ReportBook as Editor;
use App\Livewire\User\ReportBookArchive as Archive;
use App\Models\{Person, ReportBook, ReportBookEntry, User};
use App\Services\ReportBook\{ArchivedReportBookPdf, ReportBookPdfContext};
use App\Support\ParticipantReportBookAccess as Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Http, Notification, Queue, Schema, Storage};
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportBookLocationParticipantTest extends TestCase
{
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
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Queue::fake(); Notification::fake(); Http::preventStrayRequests(); Storage::fake('private');
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->default('guest');
            $t->integer('status')->default(1); $t->timestamps();
        });
        Schema::create('persons', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('person_id'); $t->integer('institut_id'); $t->string('role')->default('guest');
            $t->json('programdata')->nullable(); $t->json('statusdata')->nullable(); $t->timestamp('last_api_update')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('type'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        foreach (['2025_09_10_152938_create_courses_table.php', '2025_09_10_152939_create_course_days_table.php',
            '2025_10_07_164445_create_course_participant_enrollments_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('course_days', function (Blueprint $t) { $t->text('documentation_addendum')->nullable(); $t->integer('documentation_addendum_status')->default(0); });
        Schema::create('coaching_contracts', function (Blueprint $t) { $t->id(); $t->integer('participant_person_id'); $t->integer('course_id')->nullable(); });
        Schema::create('report_books', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('course_id'); $t->string('massnahme_id')->nullable(); $t->string('title')->nullable(); $t->json('settings')->nullable(); $t->timestamps();
        });
        Schema::create('report_book_entries', function (Blueprint $t) {
            $t->id(); $t->integer('report_book_id'); $t->integer('course_day_id'); $t->date('entry_date');
            $t->text('text')->nullable(); $t->integer('status')->default(0); $t->timestamp('submitted_at')->nullable(); $t->timestamps();
        });
        Schema::create('files', function (Blueprint $t) {
            $t->id(); $t->nullableMorphs('fileable'); $t->string('type'); $t->string('disk')->nullable(); $t->string('path')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_10_19_180000_create_report_book_location_policies.php'))->up();
    }

    public function test_disabled_location_without_approved_entries_is_hidden_even_with_empty_headers_or_drafts(): void
    {
        [$user, $person] = $this->person();
        $course = $this->course($person);
        $book = $this->book($user, $course);
        $this->policy(1, false);
        $this->assertFalse(Access::canUse($user));
        $this->entry($book, 0); $this->entry($book, 1); $this->entry($book, 3);
        $this->assertFalse(Access::showNavigation($user));
        $this->assertSame([], Access::courseIds($user));
        Livewire::actingAs($user)->test(Editor::class)->assertForbidden();
        Livewire::actingAs($user)->test(Archive::class)->assertForbidden();
    }

    public function test_approved_archive_uses_book_ids_and_does_not_create_or_change_records(): void
    {
        [$user, $person] = $this->person();
        $course = $this->course($person);
        $book = $this->book($user, $course, 'first'); $approved = $this->entry($book, 2, 'Approved original');
        $this->entry($book, 1, 'Pending secret'); $this->entry($book, 0, 'Draft secret');
        $otherBook = $this->book($user, $course, 'second'); $this->entry($otherBook, 2, 'Second measure');
        $this->policy(1, false);
        DB::table('course_participant_enrollments')->where('course_id', $course)->update(['is_active' => 0, 'deleted_at' => now()]);
        $before = DB::table('report_book_entries')->get()->toJson();
        $this->assertTrue(Access::showNavigation($user));
        Livewire::actingAs($user)->test(Editor::class)->assertRedirect(route('reportbook.archive'));
        Livewire::actingAs($user)->test(Archive::class, ['bookId' => $book->id])
            ->assertSee('Approved original')->assertDontSee('Pending secret')->assertDontSee('Draft secret')
            ->call('selectBook', $otherBook->id)->assertSee('Second measure')->assertDontSee('Approved original');
        $this->assertDatabaseCount('report_books', 2);
        $this->assertSame($before, DB::table('report_book_entries')->get()->toJson());
        Queue::assertNothingPushed();
    }

    public function test_mixed_institutes_cannot_use_an_enabled_person_to_edit_a_shared_feri_book(): void
    {
        [$user, $first] = $this->person();
        [, $second] = $this->person(2, $user);
        $shared = $this->course($first, 'ferien'); $normal = $this->course($second);
        DB::table('course_participant_enrollments')->insert(['course_id' => $shared, 'person_id' => $second->id, 'is_active' => 1]);
        $book = $this->book($user, $shared); $entry = $this->entry($book, 2);
        $this->policy(1, false);
        $this->assertSame([$normal], Access::courseIds($user));
        $this->assertFalse(Access::canAccessBook($user, $book));
        $this->assertTrue(Access::canViewArchivedBook($user, $book));
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $shared; $page->selectedCourseDayId = $entry->course_day_id; $page->text = 'Forbidden';
        $page->save(); $page->submit(); $page->importTutorDocToDraft();
        $this->assertSame('Original entry', $entry->fresh()->text);
        $this->assertSame(2, $entry->fresh()->status);
    }

    public function test_stale_editor_ai_signature_and_callback_cannot_write_after_deactivation(): void
    {
        [$user, $person] = $this->person(); $course = $this->course($person); $book = $this->book($user, $course);
        $entry = $this->entry($book, 0); $this->entry($book, 2);
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $course; $page->selectedCourseDayId = $entry->course_day_id;
        $page->text = 'Changed'; $page->pendingSignatureAction = 'submit';
        $assistant = new ReportBookAiAssistant(); $assistant->entry = $entry; $assistant->optimizedText = 'Changed by AI';
        $signature = new SignatureForm(); $signature->fileableType = ReportBook::class; $signature->fileableId = $book->id; $signature->fileType = 'sign_reportbook_participant';
        $this->policy(1, false);
        $page->save(); $page->submit(); $page->importTutorDocToDraft();
        $this->assertNull($page->handleSignatureCompleted(['fileableType' => ReportBook::class, 'fileableId' => $book->id, 'fileType' => 'sign_reportbook_participant']));
        $this->denied(fn () => $assistant->saveToEntry());
        $this->denied(fn () => $assistant->generateSuggestion());
        $this->denied(fn () => $signature->save());
        $this->assertSame('Original entry', $entry->fresh()->text);
        $this->assertSame(0, $entry->fresh()->status);
        $this->assertDatabaseCount('files', 0);
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_only_shared_conflicting_course_is_hidden_without_history_and_routes_to_archive_with_approved_history(): void
    {
        [$user, $first] = $this->person(); [, $second] = $this->person(2, $user);
        $course = $this->course($first, 'ferien');
        DB::table('course_participant_enrollments')->insert(['course_id' => $course, 'person_id' => $second->id, 'is_active' => 1]);
        $this->policy(1, false);
        $this->assertFalse(Access::canUseEditor($user));
        $this->assertFalse(Access::showNavigation($user));
        $this->entry($this->book($user, $course), 2);
        $this->assertTrue(Access::showNavigation($user));
        $this->assertSame(route('reportbook.archive'), Access::entryUrl($user));
        Livewire::actingAs($user)->test(Editor::class)->assertRedirect(route('reportbook.archive'));
    }

    public function test_navigation_does_not_combine_disabled_education_person_with_enabled_non_education_person(): void
    {
        [$user] = $this->person(); [, $second] = $this->person(2, $user);
        DB::table('persons')->where('id', $second->id)->update(['programdata' => json_encode(['tn_baust' => []])]);
        $this->policy(1, false);
        $this->assertTrue(Access::canUseEditor($user));
        $this->assertFalse(Access::showNavigation($user));
    }

    public function test_historical_conflicting_enrollment_cannot_expose_unapproved_entries_or_create_new_headers(): void
    {
        [$user, $first] = $this->person(); [, $second] = $this->person(2, $user);
        $course = $this->course($first, 'ferien');
        DB::table('course_participant_enrollments')->where('person_id', $first->id)->update(['is_active' => 0]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $course, 'person_id' => $second->id, 'is_active' => 1]);
        $this->policy(1, false);
        $this->assertSame([], Access::courseIds($user));
        $this->assertFalse(Access::canUseEditor($user));
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $course; $page->save();
        $this->assertDatabaseCount('report_books', 0);

        $book = $this->book($user, $course); $draft = $this->entry($book, 0, 'Historical hidden draft'); $this->entry($book, 2, 'Historical approval');
        $page->selectedCourseDayId = $draft->course_day_id; $page->loadCurrentEntry(); $page->loadCourseDays();
        $this->assertSame('', $page->text); $this->assertSame([], $page->courseDays);
        $this->assertNull($page->exportReportEntry()); $this->assertNull($page->exportReportModule()); $this->assertNull($page->exportReportAll());
        Livewire::actingAs($user)->test(Archive::class, ['bookId' => $book->id])->assertSee('Historical approval')->assertDontSee('Historical hidden draft');
    }

    public function test_system_approved_entry_remains_immutable_after_reactivation_while_new_entries_remain_editable(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $entry = $this->entry($book, 2);
        $this->audit($book, $entry);
        DB::table('course_days')->where('id', $entry->course_day_id)->update(['notes' => 'Tutor documentation']);
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $book->course_id; $page->selectedCourseDayId = $entry->course_day_id; $page->text = 'Forbidden change';
        $page->save(); $page->submit(); $page->importTutorDocToDraft();
        $assistant = new ReportBookAiAssistant(); $assistant->entry = $entry; $assistant->optimizedText = 'Forbidden AI change';
        $this->denied(fn () => $assistant->saveToEntry());
        $this->denied(fn () => $assistant->openForEntry($entry->id));
        $this->assertSame('Original entry', $entry->fresh()->text); $this->assertSame(2, $entry->fresh()->status);
        $this->assertSame([$entry->id], Access::archivedEntries($book)->pluck('id')->all());
        $draft = $this->entry($book, 0);
        $page->selectedCourseDayId = $draft->course_day_id; $page->text = 'Allowed new entry'; $page->save();
        $this->assertSame('Allowed new entry', $draft->fresh()->text);
        Livewire::actingAs($user)->test(Editor::class, ['selectedCourseId' => $book->course_id, 'selectedCourseDayId' => $entry->course_day_id])
            ->assertSee('bleibt unverändert erhalten')->assertDontSeeHtml('wire:click="save"')->assertDontSeeHtml('wire:click="importTutorDocToDraft"');
    }

    public function test_archive_rejects_foreign_books_coaching_and_unapproved_entry_ids(): void
    {
        [$user, $person] = $this->person(); $course = $this->course($person); $book = $this->book($user, $course);
        $this->entry($book, 2); $draft = $this->entry($book, 0);
        [$other, $otherPerson] = $this->person(); $foreign = $this->book($other, $this->course($otherPerson)); $this->entry($foreign, 2);
        $coaching = $this->book($user, $this->course($person, 'coaching')); $this->entry($coaching, 2);
        $this->policy(1, false); $this->actingAs($user);
        $archive = new Archive(); $archive->bookId = $book->id;
        $this->denied(fn () => $archive->selectBook($foreign->id));
        $this->denied(fn () => $archive->selectBook($coaching->id));
        $this->denied(fn () => $archive->exportEntry($draft->id));
        $this->assertSame([$book->id], Access::archiveBooks($user)->modelKeys());
    }

    public function test_failed_participant_write_rolls_back_entry_and_preserves_existing_signature_file(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $entry = $this->entry($book, 0);
        Storage::disk('private')->put('signatures/existing.png', 'historical signature');
        DB::table('files')->insert(['fileable_type' => ReportBook::class, 'fileable_id' => $book->id,
            'type' => 'sign_reportbook_participant', 'path' => 'signatures/existing.png', 'disk' => 'private']);
        $reject = true; $sawTransaction = false;
        ReportBookEntry::saving(function ($model) use (&$reject, &$sawTransaction, $entry) {
            if ($reject && $model->id === $entry->id) {
                $sawTransaction = DB::transactionLevel() > 0;
                throw new \RuntimeException('Synthetic entry failure');
            }
        });
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $book->course_id; $page->selectedCourseDayId = $entry->course_day_id; $page->text = 'Must roll back';
        try { $page->save(); $this->fail('Expected write failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic entry failure', $e->getMessage()); }
        finally { $reject = false; }
        $this->assertTrue($sawTransaction);
        $this->assertSame('Original entry', $entry->fresh()->text);
        $this->assertDatabaseCount('files', 1);
        Storage::disk('private')->assertExists('signatures/existing.png');
        $page->save();
        $this->assertSame('Must roll back', $entry->fresh()->text);
        $this->assertDatabaseCount('files', 0);
        Storage::disk('private')->assertMissing('signatures/existing.png');
    }

    public function test_failed_signature_persistence_removes_only_the_new_private_file(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $this->entry($book, 0);
        Storage::disk('private')->put('signatures/historical.png', 'historical signature');
        $image = imagecreatetruecolor(300, 80); imagestring($image, 5, 10, 20, 'Synthetic signature', imagecolorallocate($image, 255, 255, 255));
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $reject = true; $sawTransaction = false;
        \App\Models\File::creating(function () use (&$reject, &$sawTransaction) {
            if ($reject) {
                $sawTransaction = DB::transactionLevel() > 0;
                throw new \RuntimeException('Synthetic signature failure');
            }
        });
        $this->actingAs($user);
        $signature = new SignatureForm(); $signature->fileableType = ReportBook::class; $signature->fileableId = $book->id;
        $signature->fileType = 'sign_reportbook_participant'; $signature->signatureDataUrl = 'data:image/png;base64,'.base64_encode($bytes);
        try { $signature->save(); $this->fail('Expected signature failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic signature failure', $e->getMessage()); }
        finally { $reject = false; }
        $this->assertTrue($sawTransaction);
        $this->assertDatabaseCount('files', 0);
        $this->assertSame(['signatures/historical.png'], Storage::disk('private')->allFiles());
    }

    public function test_editing_a_different_draft_preserves_approved_signatures_and_pdf_never_uses_later_replacements(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person));
        $manual = $this->entry($book, 2, 'Manual original'); $draft = $this->entry($book, 0);
        $oldParticipant = $this->signatureFile($book, 'participant', 'old participant');
        $oldTrainer = $this->signatureFile($book, 'trainer', 'old trainer');
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $book->course_id; $page->selectedCourseDayId = $draft->course_day_id; $page->text = 'New draft'; $page->save();
        $audit = DB::table('report_book_policy_audit')->where('event', 'manual_signatures_preserved')->first();
        $this->assertNotNull($audit); $this->assertSame($manual->id, $audit->entry_id);
        $this->assertSame(2, $manual->fresh()->status); $this->assertSame('Manual original', $manual->fresh()->text);
        $this->assertNull($book->fresh()->participantSignatureFile());
        $this->assertNull($book->fresh()->trainerSignatureFile());
        Storage::disk('private')->assertExists($oldParticipant->path); Storage::disk('private')->assertExists($oldTrainer->path);
        $this->assertNull($page->exportReportEntry());
        $this->assertSame('export_entry', $page->pendingSignatureAction);
        $newParticipant = $this->signatureFile($book, 'participant', 'new participant');
        $this->signatureFile($book, 'trainer', 'new trainer');
        $prepared = $book->fresh()->load('entries'); app(ReportBookPdfContext::class)->prepare($prepared);
        $evidence = $prepared->entries->firstWhere('id', $manual->id)->signature_evidence;
        $this->assertSame($oldParticipant->id, $evidence['participant']['file_id']);
        $this->assertSame($oldTrainer->id, $evidence['trainer']['file_id']);
        $this->assertTrue(Access::canViewArchivedBook($user, $prepared));
        $this->assertFalse(Access::canWriteEntry($user, $manual->fresh()));
        $this->assertSame($newParticipant->id, $prepared->participantSignatureFile->id);
        $html = view('pdf.report-book', ['mode' => 'all', 'books' => collect([$prepared]), 'user' => $user])->render();
        $this->assertSame(2, substr_count($html, '<div class="page">'));
        $this->assertStringContainsString($evidence['participant']['image'], $html);
        $page->text = 'Another draft'; $page->save();
        $this->assertSame($audit->payload, DB::table('report_book_policy_audit')->where('id', $audit->id)->value('payload'));
        $this->assertDatabaseCount('files', 4);
    }

    public function test_personal_signature_evidence_cannot_be_deleted_by_model_file_pool_or_storage_api(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $this->entry($book, 2);
        $file = $this->signatureFile($book, 'participant', 'Protected proof');
        $this->denied(fn () => $file->delete(), 409);
        $pool = new \App\Livewire\Tools\FilePools\ManageFilePools();
        $this->denied(fn () => $pool->deleteFile($file->id), 409);
        \App\Models\Setting::setValue('api', 'base_api_key', 'synthetic-test-key');
        $request = \Illuminate\Http\Request::create('/api/storage', 'DELETE', ['path' => $file->path, 'visibility' => 'private']);
        $request->headers->set('X-API-KEY', 'synthetic-test-key');
        $response = app(\App\Http\Controllers\Api\AdminStorageController::class)->destroy($request);
        $this->assertSame(409, $response->getStatusCode());
        Storage::disk('private')->assertExists($file->path); $this->assertDatabaseCount('files', 1);
    }

    public function test_manual_and_system_signature_snapshot_hashes_reject_tampering_and_missing_files(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person));
        $manual = $this->entry($book, 2); $system = $this->entry($book, 2);
        $file = $this->signatureFile($book, 'participant', 'Original signature');
        $this->audit($book, $system);
        Access::withWriteLock($user, $book, fn ($locked) => \App\Services\ReportBook\ReportBookSignatureEvidence::preserveAndInvalidate($locked));
        Storage::disk('private')->put($file->path, 'tampered bytes');
        foreach ([$manual, $system] as $entry) {
            $prepared = $book->fresh()->setRelation('entries', new \Illuminate\Database\Eloquent\Collection([$entry->fresh()]));
            $this->denied(fn () => app(ReportBookPdfContext::class)->prepare($prepared), 409);
        }
        Storage::disk('private')->delete($file->path);
        foreach ([$manual, $system] as $entry) {
            $prepared = $book->fresh()->setRelation('entries', new \Illuminate\Database\Eloquent\Collection([$entry->fresh()]));
            $this->denied(fn () => app(ReportBookPdfContext::class)->prepare($prepared), 409);
        }
    }

    public function test_signature_missing_at_snapshot_remains_empty_even_if_a_file_appears_later(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person));
        $manual = $this->entry($book, 2); $system = $this->entry($book, 2);
        $file = $this->signatureFile($book, 'participant', 'Later signature');
        $bytes = Storage::disk('private')->get($file->path); Storage::disk('private')->delete($file->path);
        Access::withWriteLock($user, $book, fn ($locked) => \App\Services\ReportBook\ReportBookSignatureEvidence::preserveAndInvalidate($locked));
        $this->audit($book, $system);
        Storage::disk('private')->put($file->path, $bytes);
        $prepared = $book->fresh()->load('entries'); app(ReportBookPdfContext::class)->prepare($prepared);
        $this->assertNull($prepared->entries->firstWhere('id', $manual->id)->signature_evidence['participant']['image']);
        $this->assertNull($prepared->entries->firstWhere('id', $system->id)->signature_evidence['participant']['image']);
    }

    public function test_active_normal_course_keeps_existing_editing_and_no_partial_archive(): void
    {
        [$user, $person] = $this->person(); $course = $this->course($person); $book = $this->book($user, $course);
        $entry = $this->entry($book, 0); $this->entry($book, 2);
        $this->assertFalse(Access::hasArchive($user));
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $course; $page->selectedCourseDayId = $entry->course_day_id; $page->text = 'Normal update'; $page->save();
        $this->assertSame('Normal update', $entry->fresh()->text);
        $this->assertTrue(Access::canUseEditor($user));
    }

    public function test_historical_approved_entries_remain_readable_without_current_enrollment(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $this->entry($book, 2);
        DB::table('course_participant_enrollments')->update(['is_active' => 0]);
        $this->assertTrue(Access::canViewArchivedBook($user, $book));
        $this->assertFalse(Access::canAccessBook($user, $book));
        $this->assertFalse(Access::canUseEditor($user));
        $this->assertSame(route('reportbook.archive'), Access::entryUrl($user));
        Livewire::actingAs($user)->test(Editor::class)->assertRedirect(route('reportbook.archive'));
    }

    public function test_user_without_person_or_history_keeps_the_existing_empty_state(): void
    {
        $user = User::create(['name' => 'No person yet', 'email' => 'unlinked@example.test', 'role' => 'guest', 'status' => 1]);
        $this->assertTrue(Access::canUseEditor($user));
        $this->assertFalse(Access::showNavigation($user));
        $this->assertSame([], Access::courseIds($user));
    }

    public function test_archival_pdf_contains_only_approved_entries_and_never_requests_a_signature(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person));
        $approved = $this->entry($book, 2); $this->entry($book, 0, 'Never export this'); $this->policy(1, false);
        Pdf::shouldReceive('loadView')->once()->with('pdf.report-book', \Mockery::on(function ($data) use ($approved) {
            $this->assertTrue($data['approvedExcerpt']);
            $this->assertSame([$approved->id], $data['books']->first()->entries->modelKeys());
            $this->assertNull($data['books']->first()->participantSignatureFile);
            return true;
        }))->andReturnSelf();
        $response = app(ArchivedReportBookPdf::class)->download($user, collect([$book]));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertDatabaseCount('files', 0); Queue::assertNothingPushed();
    }

    public function test_pdf_stamp_uses_immutable_entry_audit_and_berlin_date_not_current_configuration(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person));
        $manual = $this->entry($book, 2, 'Manual approved'); $automatic = $this->entry($book, 2, 'System approved');
        $this->signatureFile($book, 'trainer', 'AUSBILDER ALT / TEST');
        $this->audit($book, $automatic);
        $book->load('entries'); app(ReportBookPdfContext::class)->prepare($book);
        $this->assertNull($book->entries->firstWhere('id', $manual->id)->system_release);
        $release = $book->entries->firstWhere('id', $automatic->id)->system_release;
        $this->assertSame('20.10.2026', $release['date']);
        $this->assertStringStartsWith('data:image/png;base64,', $release['image']);
        $this->assertTrue(Access::canViewArchivedBook($user, $book));
        $this->assertSame([$automatic->id], Access::archivedEntries($book)->pluck('id')->all());
        $html = view('pdf.report-book', ['mode' => 'all', 'books' => collect([$book]), 'user' => $user, 'approvedExcerpt' => true])->render();
        $this->assertSame(1, substr_count($html, 'alt="Standortstempel"'));
        $this->assertStringNotContainsString('Systemfreigabe aufgrund Standortregel', $html);
        $this->assertSame(2, substr_count($html, '<div class="page">'));
        $this->assertStringNotContainsString('Freigegeben am', $html);
        $this->assertStringContainsString('Freigegebener Ausschnitt', $html);

        $entry = $book->entries->firstWhere('id', $automatic->id);
        foreach (['all', 'module', 'single'] as $mode) {
            $rendered = $mode === 'all' ? $html : view('pdf.report-book', [
                'mode' => $mode, 'user' => $user, 'course' => $book->course,
                'entry' => $entry, 'entries' => collect([$entry]),
            ])->render();
            $document = new \DOMDocument();
            $document->loadHTML($rendered, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $stampCell = $xpath->query('//td[contains(@class, "sig-cell")][.//img[@alt="Standortstempel"]]');
            $this->assertSame(1, $stampCell->length, $mode);
            $this->assertSame('Unterschrift', trim($xpath->evaluate('string(preceding-sibling::td)', $stampCell->item(0))), $mode);
            $this->assertSame('20.10.2026', trim($xpath->evaluate('string(../preceding-sibling::tr[1]/td[@class="val"])', $stampCell->item(0))), $mode);
            foreach ($xpath->query('//img[@alt="Unterschrift Ausbilder"]') as $signature) {
                $this->assertSame('18.10.2026', trim($xpath->evaluate('string(ancestor::tr[1]/preceding-sibling::tr[1]/td[@class="val"])', $signature)), $mode);
            }
        }
    }

    public function test_changed_stamp_or_mismatched_audit_blocks_export_instead_of_fabricating_evidence(): void
    {
        [$user, $person] = $this->person(); $book = $this->book($user, $this->course($person)); $entry = $this->entry($book, 2);
        $this->audit($book, $entry);
        Storage::disk('private')->put('report-book-policy/stamps/original.png', 'changed');
        $this->denied(fn () => app(ReportBookPdfContext::class)->prepare($book->fresh()->load('entries')), 409);
        DB::table('report_book_policy_audit')->update(['entry_id' => $entry->id + 10]);
        $this->denied(fn () => app(ReportBookPdfContext::class)->prepare($book->fresh()->load('entries')), 409);
    }

    public function test_actual_pdf_renders_mixed_manual_and_system_approvals_without_open_entries(): void
    {
        [$user, $person] = $this->person();
        $book = $this->book($user, $this->course($person));
        $this->entry($book, 2, '<p>Manuell freigegebener Eintrag. Grundlagen der Netzwerktechnik und Dokumentation der durchgeführten Übungen.</p>');
        $first = $this->entry($book, 2, '<p>Systemfreigabe mit der ersten Stempelversion. Analyse eines lokalen Schulungsnetzes und Erstellung eines übersichtlichen Prüfprotokolls.</p>');
        $second = $this->entry($book, 2, '<p>Systemfreigabe mit der zweiten Stempelversion. Wiederholung der Unterrichtsinhalte und praktische Übungen zur Fehleranalyse.</p>');
        $this->entry($book, 1, 'OFFENER EINTRAG DARF NICHT IM PDF ERSCHEINEN');
        $draft = $this->entry($book, 0, 'DRAFT DARF NICHT IM PDF ERSCHEINEN');
        $this->signatureFile($book, 'participant', 'TN ALT / TEST');
        $this->signatureFile($book, 'trainer', 'AUSBILDER ALT / TEST');
        $this->audit($book, $first, 10); $this->audit($book, $second, 11);
        $this->actingAs($user);
        $page = new Editor(); $page->selectedCourseId = $book->course_id; $page->selectedCourseDayId = $draft->course_day_id;
        $page->text = 'DRAFT DARF NICHT IM PDF ERSCHEINEN'; $page->save();
        $this->signatureFile($book, 'participant', 'TN NEU / NICHT IM ARCHIV');
        $this->signatureFile($book, 'trainer', 'AUSBILDER NEU / TEST');
        $this->policy(1, false);
        $response = app(ArchivedReportBookPdf::class)->download($user, collect([$book]));
        ob_start();
        try { $response->sendContent(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
        if ($path = getenv('REPORTBOOK_PDF_QA_PATH')) {
            $this->assertTrue(is_dir(dirname($path)), 'The explicitly selected QA output folder must exist.');
            $this->assertSame(strlen($bytes), file_put_contents($path, $bytes));
        }
    }

    private function person(int $institute = 1, ?User $user = null): array
    {
        $user ??= User::create(['name' => 'Test participant', 'email' => 'participant'.User::count().'@example.test', 'role' => 'guest', 'status' => 1]);
        $person = Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => 'test-'.Person::count(), 'institut_id' => $institute,
            'programdata' => ['tn_baust' => array_fill(0, 20, [])], 'last_api_update' => now()]));
        return [$user, $person];
    }

    private function course(Person $person, string $type = 'basic'): int
    {
        $id = DB::table('courses')->insertGetId(['title' => $type, 'klassen_id' => 'test-'.DB::table('courses')->count(), 'type' => $type,
            'planned_start_date' => '2026-10-01', 'planned_end_date' => '2026-10-31']);
        DB::table('course_participant_enrollments')->insert(['course_id' => $id, 'person_id' => $person->id, 'is_active' => 1]);
        return $id;
    }

    private function book(User $user, int $course, ?string $measure = null): ReportBook
    {
        return ReportBook::create(['user_id' => $user->id, 'course_id' => $course, 'massnahme_id' => $measure, 'title' => 'Existing book']);
    }

    private function entry(ReportBook $book, int $status, string $text = 'Original entry'): ReportBookEntry
    {
        $date = \Carbon\CarbonImmutable::parse('2026-10-19')->addDays(DB::table('course_days')->where('course_id', $book->course_id)->count())->toDateString();
        $day = DB::table('course_days')->insertGetId(['course_id' => $book->course_id, 'date' => $date]);
        return ReportBookEntry::create(['report_book_id' => $book->id, 'course_day_id' => $day, 'entry_date' => $date, 'status' => $status, 'text' => $text]);
    }

    private function policy(int $institute, bool $required): void
    {
        $id = DB::table('report_book_institute_policies')->insertGetId(['institut_id' => $institute, 'version' => 1]);
        DB::table('report_book_policy_revisions')->insert(['policy_id' => $id, 'revision' => 1, 'required' => $required,
            'stamp_enabled' => false, 'effective_at' => '2020-01-01 00:00:00', 'created_at' => now()]);
    }

    private function signatureFile(ReportBook $book, string $role, string $text): \App\Models\File
    {
        $image = imagecreatetruecolor(300, 80); imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 10, 20, $text, imagecolorallocate($image, 35, 70, 110));
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $path = 'signatures/'.$role.'-'.hash('sha256', $text).'.png'; Storage::disk('private')->put($path, $bytes);
        $id = DB::table('files')->insertGetId(['fileable_type' => ReportBook::class, 'fileable_id' => $book->id,
            'type' => 'sign_reportbook_'.$role, 'disk' => 'private', 'path' => $path, 'created_at' => '2026-10-18 12:00:00', 'updated_at' => '2026-10-18 12:00:00']);
        return \App\Models\File::findOrFail($id);
    }

    private function audit(ReportBook $book, ReportBookEntry $entry, int $revision = 10): void
    {
        $image = imagecreatetruecolor(420, 110);
        $white = imagecolorallocate($image, 255, 255, 255); $ink = imagecolorallocate($image, 35, 70, 110);
        imagefill($image, 0, 0, $white); imagerectangle($image, 2, 2, 417, 107, $ink);
        imagestring($image, 5, 24, 20, 'TESTSTEMPEL BERLIN - VERSION '.$revision, $ink);
        imagestring($image, 3, 24, 49, 'SYSTEMFREIGABE / SYNTHETISCHE TESTDATEN', $ink);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $path = 'report-book-policy/stamps/'.($revision === 10 ? 'original' : 'revision-'.$revision).'.png';
        Storage::disk('private')->put($path, $bytes);
        $id = DB::table('report_book_policy_audit')->insertGetId(['event_key' => 'approved-'.$entry->id, 'event' => 'entry_approved',
            'book_id' => $book->id, 'entry_id' => $entry->id, 'revision_id' => $revision, 'created_at' => now(), 'payload' => json_encode([
                'stamp' => ['path' => $path, 'sha256' => hash('sha256', $bytes), 'revision_id' => $revision], 'approved_at' => '2026-10-19T23:30:00+00:00',
                'context' => ['signature_files' => $book->files()->get()->map(fn ($file) => [
                    'id' => $file->id, 'type' => $file->type, 'disk' => $file->disk, 'path' => $file->path,
                    'created_at' => $file->getRawOriginal('created_at'),
                    'sha256' => Storage::disk('private')->exists($file->path) ? hash('sha256', Storage::disk('private')->get($file->path)) : null,
                    'missing_at_snapshot' => ! Storage::disk('private')->exists($file->path),
                ])->all()],
            ])]);
        DB::table('report_book_entries')->where('id', $entry->id)->update(['automated_approval_audit_id' => $id]);
    }

    private function denied(callable $action, int $status = 403): void
    {
        try { $action(); $this->fail('Expected server-side denial.'); }
        catch (HttpException $e) { $this->assertSame($status, $e->getStatusCode()); }
    }
}
