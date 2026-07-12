<?php

namespace Tests\Feature;

use App\Livewire\User\MakeupExamRegistration;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MakeupExamRegistrationTest extends TestCase
{
    private User $user;

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

        $this->createTestTables();

        $this->user = User::query()->create([
            'name' => 'Test Teilnehmer',
            'email' => 'teilnehmer@example.test',
            'password' => bcrypt('test-password'),
            'role' => 'user',
            'status' => 'active',
        ]);
        $this->user->setRelation('person', null);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDatabaseConnection,
            'database.connections.sqlite.database' => $this->originalSqliteDatabase,
            'database.connections.sqlite.foreign_key_constraints' => $this->originalSqliteForeignKeyConstraints,
        ]);
        DB::setDefaultConnection($this->originalDatabaseConnection);

        parent::tearDown();
    }

    /**
     * @dataProvider makeupExamOptions
     */
    public function test_it_saves_the_configured_fee_and_modality(
        string $selection,
        string $modality,
        int $feeCents,
    ): void {
        $component = $this->validComponent($selection);

        UserRequest::withoutEvents(fn () => $component->call('save'));

        $component->assertHasNoErrors();

        $request = UserRequest::query()->sole();

        $this->assertSame($modality, $request->exam_modality);
        $this->assertSame($feeCents, $request->fee_cents);
        $this->assertSame($selection, $request->data['wiederholung']);
    }

    public static function makeupExamOptions(): array
    {
        return [
            'interne Wiederholungsprüfung' => [
                'wiederholung_1',
                UserRequest::EXAM_MODALITY_RETAKE,
                5000,
            ],
            'interne Nachprüfung' => [
                'wiederholung_2',
                UserRequest::EXAM_MODALITY_IMPROVEMENT,
                3000,
            ],
        ];
    }

    public function test_it_rejects_an_unknown_makeup_exam_option(): void
    {
        $component = $this->validComponent('unbekannte_option');

        UserRequest::withoutEvents(fn () => $component->call('save'));

        $component->assertHasErrors(['wiederholung' => 'in']);
        $this->assertSame(0, UserRequest::query()->count());
    }

    public function test_form_and_pdf_use_the_current_labels(): void
    {
        Livewire::actingAs($this->user)
            ->test(MakeupExamRegistration::class)
            ->assertSee('Interne Wiederholungsprüfung – 50,00 €')
            ->assertSee('Interne Nachprüfung – 30,00 €')
            ->assertDontSee('20,00 €')
            ->assertDontSee('40,00 €');

        $request = new UserRequest([
            'type' => UserRequest::TYPE_MAKEUP,
            'class_code' => 'TEST26',
            'module_code' => 'PHP01',
            'instructor_name' => 'Test Dozent',
            'original_exam_date' => '2026-07-01',
            'scheduled_at' => '2026-07-20 09:00:00',
            'reason' => 'unter51',
            'exam_modality' => UserRequest::EXAM_MODALITY_RETAKE,
            'fee_cents' => 5000,
        ]);

        $html = view('pdf.requests.exam-registration', [
            'request' => $request,
            'user' => $this->pdfUser(),
        ])->render();

        $this->assertStringContainsString('Interne Wiederholungsprüfung – 50,00 €', $html);
        $this->assertStringContainsString('Interne Nachprüfung – 30,00 €', $html);
        $this->assertStringNotContainsString('20,00 €', $html);
        $this->assertStringNotContainsString('40,00 €', $html);
    }

    public function test_pdf_keeps_the_persisted_fee_for_a_historic_request(): void
    {
        $request = new UserRequest([
            'type' => UserRequest::TYPE_MAKEUP,
            'exam_modality' => UserRequest::EXAM_MODALITY_RETAKE,
            'fee_cents' => 2000,
        ]);

        $html = view('pdf.requests.exam-registration', [
            'request' => $request,
            'user' => $this->pdfUser(),
        ])->render();

        $this->assertStringContainsString('Interne Wiederholungsprüfung – 20,00 €', $html);
    }

    private function validComponent(string $selection): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user)
            ->test(MakeupExamRegistration::class)
            ->set('wiederholung', $selection)
            ->set('nachKlTermin', (string) Carbon::parse('2026-07-20 09:00:00')->timestamp)
            ->set('nKlBaust', 'PHP01')
            ->set('nKlDozent', 'Test Dozent')
            ->set('nKlOrig', '2026-07-01')
            ->set('grund', 'unter51')
            ->set('klasse', 'TEST26');
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
            $table->string('title', 200)->nullable();
            $table->date('original_exam_date')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->string('module_code', 32)->nullable();
            $table->string('instructor_name', 120)->nullable();
            $table->string('reason', 64)->nullable();
            $table->boolean('with_attest')->nullable();
            $table->unsignedInteger('fee_cents')->nullable();
            $table->string('exam_modality', 32)->nullable();
            $table->string('status', 24)->default('pending');
            $table->dateTimeTz('submitted_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }
}
