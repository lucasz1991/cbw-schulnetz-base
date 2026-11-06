<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Services\CourseRatingsPdfExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

class CourseRatingsPdfTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'queue.default' => 'sync',
                'session.driver' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('teilnehmer_nr')->nullable();
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2025_09_10_152938_create_courses_table.php'))->up();
        Schema::create('course_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('course_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('skip_course_rating')->nullable();
            foreach (['kb', 'sa', 'il', 'do'] as $category) {
                foreach ([1, 2, 3] as $number) {
                    $table->unsignedTinyInteger($category.'_'.$number)->nullable();
                }
            }
            $table->text('message')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_06_120000_add_lecturer_plan_to_courses.php'))->up();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Synthetic anonymous participant'],
            ['id' => 2, 'name' => 'Synthetic named participant'],
        ]);
        DB::table('persons')->insert([
            ['id' => 1, 'user_id' => 1, 'teilnehmer_nr' => 'PRIVATE-ANONYMOUS-NUMBER', 'vorname' => 'Ada', 'nachname' => 'Muster'],
            ['id' => 2, 'user_id' => 2, 'teilnehmer_nr' => 'TEST-204', 'vorname' => 'Ben', 'nachname' => 'Muster'],
            ['id' => 3, 'user_id' => null, 'teilnehmer_nr' => null, 'vorname' => 'Test', 'nachname' => 'Dozent'],
        ]);
        Course::create([
            'klassen_id' => 'synthetic-1',
            'termin_id' => 'TERM-TEST',
            'title' => 'Synthetic PDF course',
            'planned_start_date' => '2026-09-01',
            'planned_end_date' => '2026-10-02',
            'primary_tutor_person_id' => 3,
            'source_snapshot' => ['course' => ['kurzbez' => 'PDF-MODUL', 'klassen_co_ks' => 'PDF-KLASSE']],
        ]);
    }

    public function test_existing_pdf_content_anonymity_and_excluded_ratings_are_preserved(): void
    {
        $this->addRating(['user_id' => 1, 'participant_id' => 999, 'is_anonymous' => true,
            'kb_1' => 5, 'kb_2' => 3, 'message' => 'Anonymer <script>Kommentar</script>']);
        $this->addRating(['user_id' => 2, 'participant_id' => 2, 'kb_1' => 1, 'kb_3' => 4, 'message' => 'Namentlicher Kommentar']);
        $this->addRating(['user_id' => null, 'skip_course_rating' => null, 'do_1' => 4]);
        $this->addRating(['skip_course_rating' => true, 'kb_1' => 1, 'message' => 'EXCLUDED-COMMENT']);

        $data = [];
        View::composer('pdf.courses.course-ratings', function ($view) use (&$data) {
            $data = $view->getData();
        });
        $pdf = app('dompdf.wrapper');
        app()->instance('dompdf.wrapper', $pdf);
        $path = app(CourseRatingsPdfExporter::class)->generate(Course::firstOrFail());

        try {
            $this->assertNotNull($path);
            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF-', file_get_contents($path));
            $this->assertGreaterThan(1000, filesize($path));
            $this->assertSame(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
            $this->assertSame([
                'class_label' => 'PDF-KLASSE',
                'module_label' => 'PDF-MODUL',
                'tutor_name' => 'Test Dozent',
                'termin_label' => 'TERM-TEST - 01.09.2026 bis 02.10.2026',
                'ratings_count' => 3,
            ], $data['meta']);
            $this->assertSame(3.33, $data['sections']['kb']['avg']);
            $this->assertSame(4.0, $data['sections']['do']['avg']);

            $html = view('pdf.courses.course-ratings', $data)->render();
            $this->assertStringContainsString('Bemerkung von Teilnehmer-Nr: anonym', $html);
            $this->assertStringContainsString('Bemerkung von Teilnehmer-Nr: TEST-204', $html);
            $this->assertStringContainsString('&lt;script&gt;Kommentar&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('PRIVATE-ANONYMOUS-NUMBER', $html);
            $this->assertStringNotContainsString('999', $html);
            $this->assertStringNotContainsString('EXCLUDED-COMMENT', $html);
        } finally {
            if ($path) {
                @unlink($path);
            }
        }
    }

    public function test_only_skipped_ratings_do_not_create_a_pdf(): void
    {
        $this->addRating(['skip_course_rating' => true, 'message' => 'Skipped']);

        $this->assertNull(app(CourseRatingsPdfExporter::class)->generate(Course::firstOrFail()));
    }

    public function test_failed_pdf_save_removes_its_temporary_file(): void
    {
        $this->addRating(['kb_1' => 4]);
        $path = null;
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('save')->once()->andReturnUsing(function ($temporaryPath) use (&$path) {
            $path = $temporaryPath;
            throw new \RuntimeException('Synthetic PDF save failure');
        });
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);

        try {
            app(CourseRatingsPdfExporter::class)->generate(Course::firstOrFail());
            $this->fail('PDF save failure must be propagated.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic PDF save failure', $exception->getMessage());
            $this->assertNotNull($path);
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_temporal_pdf_names_the_persisted_closing_teacher_and_marks_conflicting_targets(): void
    {
        DB::table('persons')->insert(['id' => 4, 'vorname' => 'Späterer', 'nachname' => 'Kalenderdozent']);
        $course = Course::firstOrFail();
        $plan = ['status' => 'ready', 'closing_assignment_id' => 'A', 'completion_at' => '2026-10-01T16:00:00+02:00', 'assignments' => [
            ['assignment_id' => 'A', 'person_id' => 3, 'contract_id' => 'CON-A', 'mitarbeiter_id' => 'MA-A', 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-01'],
            ['assignment_id' => 'B', 'person_id' => 4, 'contract_id' => 'CON-B', 'mitarbeiter_id' => 'MA-B', 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-02'],
        ]];
        $course->forceFill(['lecturer_plan' => $plan, 'lecturer_closing_assignment_id' => 'A'])->saveQuietly();
        $this->addRating(['do_1' => 2, 'lecturer_assignment_id' => 'A', 'lecturer_person_id' => 3, 'lecturer_contract_id' => 'CON-A', 'lecturer_staff_id' => 'MA-A']);
        $expected = 'Test Dozent';
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('save')->andReturnUsing(function ($path) use ($pdf) { file_put_contents($path, '%PDF-1.4 fixture'); return $pdf; });
        Pdf::shouldReceive('loadView')->times(3)->andReturnUsing(function ($view, $data) use ($pdf, &$expected) {
            $this->assertSame('pdf.courses.course-ratings', $view);
            $this->assertSame($expected, $data['meta']['tutor_name']);

            return $pdf;
        });
        foreach (['ready', 'conflict', 'wrong-rating-target'] as $state) {
            if ($state === 'conflict') {
                $plan['status'] = 'conflict';
                $course->forceFill(['lecturer_plan' => $plan])->saveQuietly();
                $expected = 'Zuordnung ungeklärt';
            }
            if ($state === 'wrong-rating-target') {
                $plan['status'] = 'ready';
                $course->forceFill(['lecturer_plan' => $plan])->saveQuietly();
                DB::table('course_ratings')->update(['lecturer_assignment_id' => 'B']);
            }
            $path = app(CourseRatingsPdfExporter::class)->generate($course);
            $this->assertFileExists($path);
            @unlink($path);
        }
    }

    private function addRating(array $attributes): void
    {
        DB::table('course_ratings')->insert(array_replace([
            'course_id' => 1,
            'user_id' => null,
            'is_anonymous' => false,
            'skip_course_rating' => false,
            'created_at' => '2026-10-02 12:00:00',
            'updated_at' => '2026-10-02 12:00:00',
        ], $attributes));
    }
}
