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
