<?php

namespace Tests\Feature;

use App\Jobs\ReportBook\CheckReportBooks;
use App\Jobs\ReportBook\SendMissingReportBookReminderJob;
use App\Livewire\Tools\Ai\ReportBookAiAssistant;
use App\Livewire\Tools\Signatures\SignatureForm;
use App\Livewire\User\ProgramShow;
use App\Livewire\User\ReportBook as ReportBookPage;
use App\Models\{Person, ReportBook, ReportBookEntry, Setting, User};
use App\Support\ParticipantReportBookAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\{DB, Http, Notification, Queue, Schema, Storage};
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CoachingReportBookAccessTest extends TestCase
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
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Queue::fake(); Notification::fake(); Http::preventStrayRequests();
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
        Schema::create('coaching_notices', fn (Blueprint $t) => $t->id());
        Schema::create('report_books', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('course_id'); $t->string('massnahme_id')->nullable(); $t->string('title')->nullable(); $t->json('settings')->nullable(); $t->timestamps();
        });
        Schema::create('report_book_entries', function (Blueprint $t) {
            $t->id(); $t->integer('report_book_id'); $t->integer('course_day_id'); $t->date('entry_date');
            $t->text('text')->nullable(); $t->integer('status')->default(0); $t->timestamp('submitted_at')->nullable(); $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->text('message'); $t->integer('from_user'); $t->integer('to_user'); $t->integer('status'); $t->timestamps(); });
        Schema::create('files', function (Blueprint $t) { $t->id(); $t->nullableMorphs('fileable'); $t->string('type'); $t->timestamps(); $t->softDeletes(); });
    }

    public function test_coaching_only_account_has_no_report_book_even_if_education_heuristic_matches(): void
    {
        [$user, $person] = $this->person();
        $course = $this->course($person, 'coaching');
        $this->assertFalse(ParticipantReportBookAccess::canUse($user));
        $this->assertFalse(ParticipantReportBookAccess::showNavigation($user));
        $this->assertFalse(ParticipantReportBookAccess::canAccessCourse($user, $course));
        Livewire::actingAs($user)->test(ReportBookPage::class)->assertForbidden();
    }

    public function test_pre_registration_draft_without_course_cannot_open_reportbook(): void
    {
        [$user, $person] = $this->person();
        DB::table('coaching_contracts')->insert(['participant_person_id' => $person->id]);
        $this->assertFalse(ParticipantReportBookAccess::canUse($user));
        Livewire::actingAs($user)->test(ReportBookPage::class)->assertForbidden();
    }

    public function test_ordinary_parallel_contract_remains_accessible_but_coaching_is_excluded_even_when_feature_disabled(): void
    {
        [$user, $person] = $this->person();
        $coaching = $this->course($person, 'coaching');
        $ordinary = $this->course($person);
        Setting::setValue('coaching', 'enabled', false);
        $this->assertTrue(ParticipantReportBookAccess::canUse($user));
        $this->assertTrue(ParticipantReportBookAccess::showNavigation($user));
        $this->assertSame([$ordinary], ParticipantReportBookAccess::courseIds($user));
        $this->assertFalse(ParticipantReportBookAccess::canAccessCourse($user, $coaching));
    }

    public function test_legacy_vtz_e_courses_and_changed_type_of_mapped_courses_stay_excluded(): void
    {
        [$user, $person] = $this->person();
        $legacy = $this->course($person);
        DB::table('courses')->where('id', $legacy)->update(['vtz' => 'E']);
        $mapped = $this->course($person);
        DB::table('coaching_contracts')->insert(['participant_person_id' => $person->id, 'course_id' => $mapped]);
        $this->assertSame([], ParticipantReportBookAccess::courseIds($user));
        $this->assertTrue(ParticipantReportBookAccess::isCoachingCourse($legacy));
        $this->assertTrue(ParticipantReportBookAccess::isCoachingCourse($mapped));
    }

    public function test_forged_public_course_list_cannot_read_write_import_or_export_coaching_entries(): void
    {
        [$user, $person] = $this->person();
        $coaching = $this->course($person, 'coaching');
        $this->course($person);
        [$book, $entry] = $this->book($user, $coaching);
        $this->actingAs($user);
        $page = new ReportBookPage();
        $page->courses = [['id' => $coaching, 'title' => 'Forged course']];
        $page->selectedCourseId = $coaching;
        $page->selectedCourseDayId = $entry->course_day_id;
        $page->text = 'Unauthorized change';
        $page->save(); $page->submit(); $page->importTutorDocToDraft();
        $page->loadCourseDays(); $page->loadCurrentEntry();
        $this->assertSame([], $page->courseDays);
        $this->assertSame('', $page->text);
        $this->assertNull($page->getReportBookProperty());
        $this->assertNull($page->exportReportEntry());
        $this->assertNull($page->exportReportModule());
        $this->assertSame('Keep existing data', $entry->fresh()->text);
        $this->assertDatabaseCount('report_books', 1);
        $this->assertDatabaseCount('report_book_entries', 1);
    }

    public function test_aggregate_export_omits_existing_coaching_books(): void
    {
        [$user, $person] = $this->person();
        $this->book($user, $this->course($person, 'coaching'));
        $this->course($person);
        $this->actingAs($user);
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->never();
        $this->assertNull((new ReportBookPage())->exportReportAll());
        $this->assertDatabaseCount('report_books', 1);
    }

    public function test_normal_course_can_still_save_and_load_its_draft_beside_coaching(): void
    {
        [$user, $person] = $this->person();
        $coaching = $this->course($person, 'coaching');
        [$normalBook, $entry] = $this->book($user, $this->course($person));
        $this->actingAs($user);
        $page = new ReportBookPage();
        $page->selectedCourseId = $normalBook->course_id;
        $page->selectedCourseDayId = $entry->course_day_id;
        $page->text = 'Normal report updated';
        $page->save();
        $this->assertSame('Normal report updated', $entry->fresh()->text);
        $this->assertSame('Normal report updated', $page->text);
        $this->assertNotContains($coaching, array_column($page->courses, 'id'));
    }

    public function test_forged_signature_completion_cannot_submit_coaching_entry_through_normal_book(): void
    {
        [$user, $person] = $this->person();
        [, $coachingEntry] = $this->book($user, $this->course($person, 'coaching'));
        [$normalBook, $normalEntry] = $this->book($user, $this->course($person));
        DB::table('files')->insert(['fileable_type' => ReportBook::class, 'fileable_id' => $normalBook->id, 'type' => 'sign_reportbook_participant']);
        $this->actingAs($user);
        $page = new ReportBookPage();
        $page->selectedCourseId = $normalBook->course_id;
        $page->selectedCourseDayId = $normalEntry->course_day_id;
        $page->reportBookEntryId = $coachingEntry->id;
        $page->pendingSignatureAction = 'submit';
        $this->assertNull($page->handleSignatureCompleted(['fileableType' => ReportBook::class, 'fileType' => 'sign_reportbook_participant', 'fileableId' => $normalBook->id]));
        $this->assertSame(0, $coachingEntry->fresh()->status);
        $this->assertSame(0, $normalEntry->fresh()->status);
    }

    public function test_ordinary_book_remains_available_for_second_linked_person_with_current_contract(): void
    {
        [$user, $coachingPerson] = $this->person([]);
        $this->course($coachingPerson, 'coaching');
        $ordinaryPerson = Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => 'second-identity', 'institut_id' => 2,
            'programdata' => ['tn_baust' => array_fill(0, 20, [])], 'statusdata' => ['vertraege' => [['teilnehmer_id' => 'normal-active',
                'vertrag_beginn' => now()->subDays(5)->toDateString(), 'vertrag_ende' => now()->addDays(5)->toDateString(), 'is_current' => true]]]]));
        $normal = $this->course($ordinaryPerson);
        DB::table('course_participant_enrollments')->where('course_id', $normal)->update(['teilnehmer_id' => 'normal-active']);
        $expired = $this->course($ordinaryPerson);
        DB::table('course_participant_enrollments')->where('course_id', $expired)->update(['teilnehmer_id' => 'expired']);
        $this->assertTrue(ParticipantReportBookAccess::showNavigation($user));
        $this->assertSame([$normal], ParticipantReportBookAccess::courseIds($user));
    }

    public function test_ai_assistant_cannot_open_or_save_coaching_or_another_users_reportbook(): void
    {
        [$user, $person] = $this->person();
        [, $coachingEntry] = $this->book($user, $this->course($person, 'coaching'));
        [$other, $otherPerson] = $this->person();
        [, $foreignEntry] = $this->book($other, $this->course($otherPerson));
        $this->actingAs($user);
        foreach ([$coachingEntry, $foreignEntry] as $entry) {
            $assistant = new ReportBookAiAssistant();
            $this->assertForbiddenAction(fn () => $assistant->openForEntry($entry->id));
            $assistant->entry = $entry;
            $assistant->currentText = 'Forged edit';
            $this->assertForbiddenAction(fn () => $assistant->saveToEntry());
            $this->assertForbiddenAction(fn () => $assistant->generateSuggestion());
            $this->assertSame('Keep existing data', $entry->fresh()->text);
        }
        Http::assertNothingSent();
    }

    public function test_signature_cannot_create_a_coaching_reportbook_signature(): void
    {
        [$user, $person] = $this->person();
        [$book] = $this->book($user, $this->course($person, 'coaching'));
        $this->actingAs($user);
        Storage::fake('private');
        $form = new SignatureForm();
        $form->fileableType = ReportBook::class; $form->fileableId = $book->id;
        $form->fileType = 'sign_reportbook_participant';
        $form->signatureDataUrl = 'data:image/png;base64,'.base64_encode(str_repeat('x', 400));
        $this->assertForbiddenAction(fn () => $form->save());
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('files', 0);
    }

    public function test_ai_key_is_not_exposed_in_livewire_snapshot_and_tampered_endpoint_is_ignored(): void
    {
        [$user, $person] = $this->person();
        [, $entry] = $this->book($user, $this->course($person));
        Setting::setValue('ai_assistant', 'api_key', 'unit-test-secret');
        Setting::setValue('ai_assistant', 'api_url', 'https://ai.example.test/completion');
        Setting::setValue('ai_assistant', 'ai_model', 'test-model');
        Http::fake(['https://ai.example.test/completion' => Http::response(['choices' => [['message' => ['content' => '{"text":"Corrected report","comment":""}']]]])]);
        $component = Livewire::actingAs($user)->test(ReportBookAiAssistant::class);
        $this->assertArrayNotHasKey('apiKey', $component->snapshot['data']);
        $this->assertStringNotContainsString('unit-test-secret', json_encode($component->snapshot));
        $component->call('openForEntry', $entry->id)
            ->set('apiUrl', 'https://untrusted.example.test/collect')
            ->call('generateSuggestion')
            ->assertSet('optimizedText', 'Corrected report');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://ai.example.test/completion'
            && $request->hasHeader('Authorization', 'Bearer unit-test-secret'));
    }

    public function test_already_queued_coaching_reminder_and_review_job_do_nothing(): void
    {
        [$user, $person] = $this->person();
        [$book] = $this->book($user, $this->course($person, 'coaching'));
        (new SendMissingReportBookReminderJob([['to_user' => $user->id, 'report_book_id' => $book->id]]))->handle();
        (new CheckReportBooks([$book->id]))->handle();
        $this->assertDatabaseCount('messages', 0);
        $this->assertNull($book->fresh()->getSetting('missing_reportbook_reminder_sent_at'));
    }

    public function test_reminder_dispatch_keeps_normal_course_and_excludes_coaching(): void
    {
        [$user, $person] = $this->person();
        [$coachingBook] = $this->book($user, $this->course($person, 'coaching'));
        [$normalBook] = $this->book($user, $this->course($person));
        Setting::setValue('mails', 'reminder_missing_report_book', true);
        $this->artisan('reportbooks:dispatch-missing-reminders')->assertExitCode(0);
        Queue::assertPushed(SendMissingReportBookReminderJob::class, 1);
        Queue::assertPushed(SendMissingReportBookReminderJob::class, fn ($job) => array_column($job->reminders, 'report_book_id') === [$normalBook->id]);
    }

    public function test_coaching_dashboard_without_legacy_program_has_no_endless_loader_or_api_dispatch(): void
    {
        [$user, $person] = $this->person([]);
        DB::table('coaching_contracts')->insert(['participant_person_id' => $person->id]);
        Setting::setValue('coaching', 'enabled', true);
        $this->actingAs($user);
        $component = new ProgramShow(); $component->mount(); $component->pollProgram();
        $this->assertTrue($component->coachingOnly);
        $this->assertFalse($component->apiProgramLoading);
        Queue::assertNothingPushed();
    }

    public function test_ordinary_participant_without_courses_keeps_existing_empty_state(): void
    {
        [$user] = $this->person();
        $this->assertTrue(ParticipantReportBookAccess::canUse($user));
        $this->assertTrue(ParticipantReportBookAccess::showNavigation($user));
    }

    private function person(?array $program = null): array
    {
        $user = User::create(['name' => 'Test participant', 'email' => 'participant'.User::count().'@example.test', 'role' => 'guest', 'status' => 1]);
        $person = Person::withoutEvents(fn () => Person::create(['user_id' => $user->id, 'person_id' => 'test-'.$user->id, 'institut_id' => 1,
            'programdata' => $program ?? ['tn_baust' => array_fill(0, 20, [])], 'last_api_update' => now()]));
        return [$user, $person];
    }

    private function course(Person $person, string $type = 'basic'): int
    {
        $id = DB::table('courses')->insertGetId(['title' => $type, 'klassen_id' => 'test-'.DB::table('courses')->count(), 'type' => $type,
            'planned_start_date' => now()->subDays(30)->toDateString(), 'planned_end_date' => now()->subDays(20)->toDateString()]);
        DB::table('course_participant_enrollments')->insert(['course_id' => $id, 'person_id' => $person->id, 'is_active' => 1]);
        return $id;
    }

    private function book(User $user, int $courseId): array
    {
        $day = DB::table('course_days')->insertGetId(['course_id' => $courseId, 'date' => now()->subDays(20)->toDateString()]);
        $book = ReportBook::create(['user_id' => $user->id, 'course_id' => $courseId, 'title' => 'Existing book']);
        $entry = ReportBookEntry::create(['report_book_id' => $book->id, 'course_day_id' => $day, 'entry_date' => now()->subDays(20)->toDateString(), 'text' => 'Keep existing data', 'status' => 0]);
        return [$book, $entry];
    }

    private function assertForbiddenAction(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected server-side denial.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
