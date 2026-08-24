<?php

namespace Tests\Feature;

use App\Livewire\User\ExternalMakeupRegistration;
use App\Models\ExamAppointment;
use App\Models\User;
use App\Models\UserRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalMakeupRegistrationTest extends TestCase
{
    private User $user;

    private ExamAppointment $externalAppointment;

    private string $originalDatabaseConnection;

    private mixed $originalSqliteDatabase;

    private mixed $originalSqliteForeignKeyConstraints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabaseConnection = (string) config('database.default');
        $this->originalSqliteDatabase = config('database.connections.sqlite.database');
        $this->originalSqliteForeignKeyConstraints = config('database.connections.sqlite.foreign_key_constraints');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-08-23 10:00:00'));

        $this->createTestTables();

        $this->user = User::query()->create([
            'name' => 'Test Teilnehmer',
            'email' => 'teilnehmer@example.test',
            'password' => bcrypt('test-password'),
            'role' => 'guest',
            'status' => 'active',
        ]);
        $this->user->setRelation('person', null);

        $this->externalAppointment = ExamAppointment::query()->create([
            'type' => 'extern',
            'name' => 'Microsoft AZ-104',
            'preis' => '205.00',
            'dates' => [],
            'room' => 'Online',
            'pflicht_6w_anmeldung' => false,
        ]);

        ExamAppointment::query()->create([
            'type' => 'intern',
            'name' => 'Nachklausur',
            'preis' => null,
            'dates' => [['datetime' => '2026-09-10 09:30:00']],
            'room' => 'Online',
            'pflicht_6w_anmeldung' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDatabaseConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
            'database.connections.sqlite.foreign_key_constraints' => $this->originalSqliteForeignKeyConstraints,
        ]);
        DB::setDefaultConnection($this->originalDatabaseConnection);

        parent::tearDown();
    }

    public function test_it_persists_all_external_exam_pdf_values_with_the_current_price(): void
    {
        $component = $this->validComponent($this->externalAppointment);

        UserRequest::withoutEvents(fn () => $component->call('save'));

        $component->assertHasNoErrors();

        $request = UserRequest::query()->sole();

        $this->assertSame('TEST26', $request->class_label);
        $this->assertSame('Microsoft', $request->institute);
        $this->assertSame((string) $this->externalAppointment->id, $request->certification_key);
        $this->assertSame('Microsoft AZ-104', $request->certification_label);
        $this->assertSame('10.09.2026 09:30', $request->scheduled_at?->format('d.m.Y H:i'));
        $this->assertSame(20500, $request->fee_cents);
        $this->assertSame(UserRequest::REASON_CERTIFICATION_FAILED, $request->reason);

        $html = view('pdf.requests.external-exam', [
            'request' => $request,
            'user' => $this->pdfUser(),
            'course' => null,
        ])->render();

        $this->assertStringContainsString('TEST26', $html);
        $this->assertStringContainsString('Microsoft', $html);
        $this->assertStringContainsString('Microsoft AZ-104', $html);
        $this->assertStringContainsString('10.09.2026 09:30 Uhr', $html);
        $this->assertStringContainsString('205,00 €', $html);
        $this->assertStringContainsString('Ursprüngliche Prüfung nicht bestanden', $html);
        $this->assertStringNotContainsString('zert_faild', $html);
        $this->assertStringNotContainsString('certification_failed', $html);

        $pdf = Pdf::loadView('pdf.requests.external-exam', [
            'request' => $request,
            'user' => $this->pdfUser(),
            'course' => null,
        ])->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_it_rejects_an_external_exam_without_a_configured_price(): void
    {
        $withoutPrice = ExamAppointment::query()->create([
            'type' => 'extern',
            'name' => 'Zertifizierung ohne Preis',
            'preis' => null,
            'dates' => [],
            'room' => null,
            'pflicht_6w_anmeldung' => false,
        ]);

        $component = $this->validComponent($withoutPrice);

        UserRequest::withoutEvents(fn () => $component->call('save'));

        $component->assertHasErrors(['certification_key']);
        $this->assertSame(0, UserRequest::query()->count());
    }

    public function test_external_exam_accessors_prefer_explicit_legacy_data_and_label_the_legacy_reason(): void
    {
        $request = new UserRequest([
            'institute' => 'Current Institution',
            'certification_label' => 'Current Exam',
            'scheduled_at' => '2026-09-10 09:30:00',
            'fee_cents' => 20500,
            'reason' => UserRequest::LEGACY_REASON_CERTIFICATION_FAILED,
            'data' => [
                'external_institution' => 'Explicit Institution',
                'external_exam_name' => 'Explicit Exam',
                'external_exam_date' => '2026-10-12 11:45:00',
                'external_exam_fee_cents' => 9900,
            ],
        ]);

        $this->assertSame('Explicit Institution', $request->external_exam_institution);
        $this->assertSame('Explicit Exam', $request->external_exam_name);
        $this->assertSame('2026-10-12 11:45:00', $request->external_exam_date);
        $this->assertSame('99,00 €', $request->external_exam_fee_formatted);
        $this->assertSame('Ursprüngliche Prüfung nicht bestanden', $request->reason_label);
    }

    private function validComponent(ExamAppointment $appointment): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user)
            ->test(ExternalMakeupRegistration::class)
            ->set('klasse', ' TEST26 ')
            ->set('external_institution', ' Microsoft ')
            ->set('certification_key', (string) $appointment->id)
            ->set('scheduled_at', (string) Carbon::parse('2026-09-10 09:30:00')->timestamp)
            ->set('reason', UserRequest::REASON_CERTIFICATION_FAILED);
    }

    private function pdfUser(): object
    {
        return (object) [
            'name' => 'Test Teilnehmer',
            'person' => (object) [
                'nachname' => 'Teilnehmer',
                'vorname' => 'Test',
                'geburt_datum' => Carbon::parse('2000-01-01'),
                'teilnehmer_nr' => 'TN-1',
            ],
        ];
    }

    private function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('current_team_id')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('exam_appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->decimal('preis', 10, 2)->nullable();
            $table->json('dates');
            $table->string('room')->nullable();
            $table->boolean('pflicht_6w_anmeldung')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('general');
            $table->string('class_code', 32)->nullable();
            $table->string('institute', 64)->nullable();
            $table->string('title', 200)->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->string('reason', 64)->nullable();
            $table->boolean('with_attest')->nullable();
            $table->unsignedInteger('fee_cents')->nullable();
            $table->string('exam_modality', 32)->nullable();
            $table->string('certification_key', 64)->nullable();
            $table->string('certification_label', 180)->nullable();
            $table->string('class_label', 64)->nullable();
            $table->string('email_priv', 190)->nullable();
            $table->string('status', 24)->default('pending');
            $table->dateTimeTz('submitted_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }
}
