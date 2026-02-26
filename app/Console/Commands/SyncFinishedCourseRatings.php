<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\ApiUvs\CourseApiServices\CourseRatingsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFinishedCourseRatings extends Command
{
    protected $signature = 'course-ratings:sync-finished
                            {--course-id= : Nur einen Kurs (course_id) verarbeiten}
                            {--dry-run : Nur anzeigen, nichts synchronisieren}';

    protected $description = 'Synchronisiert alle Kurse mit vorhandenen Bewertungen zur UVS-API.';

    public function handle(CourseRatingsSyncService $syncService): int
    {
        $debugLogs = (bool) config('api_sync.debug_logs', false);
        $dryRun = (bool) $this->option('dry-run');
        $courseIdOption = $this->option('course-id');

        $query = Course::query()
            ->whereHas('ratings');

        if ($courseIdOption !== null && $courseIdOption !== '') {
            $query->where('id', (int) $courseIdOption);
        }

        $courses = $query
            ->withCount('ratings')
            ->orderBy('id')
            ->get();

        if ($courses->isEmpty()) {
            $this->info('Keine beendeten Kurse mit Bewertungen gefunden.');
            return self::SUCCESS;
        }

        $this->info('Gefundene Kurse: ' . $courses->count());

        $okCount = 0;
        $failCount = 0;

        foreach ($courses as $course) {
            $label = sprintf(
                '#%d (%s), ratings=%d, ende=%s',
                $course->id,
                (string) ($course->klassen_id ?? 'n/a'),
                (int) $course->ratings_count,
                (string) optional($course->planned_end_date)->toDateString()
            );

            if ($dryRun) {
                $this->line('[dry-run] ' . $label);
                continue;
            }

            $ok = $syncService->syncToRemote($course);

            if ($ok) {
                $okCount++;
                $this->info('OK: ' . $label);

                if ($debugLogs) {
                    Log::info('SyncFinishedCourseRatings: course synced.', [
                        'course_id'      => $course->id,
                        'klassen_id'     => $course->klassen_id,
                        'ratings_count'  => (int) $course->ratings_count,
                        'planned_end'    => optional($course->planned_end_date)->toDateString(),
                    ]);
                }
            } else {
                $failCount++;
                $this->error('FEHLER: ' . $label);

                if ($debugLogs) {
                    Log::warning('SyncFinishedCourseRatings: course sync failed.', [
                        'course_id'      => $course->id,
                        'klassen_id'     => $course->klassen_id,
                        'ratings_count'  => (int) $course->ratings_count,
                        'planned_end'    => optional($course->planned_end_date)->toDateString(),
                    ]);
                }
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run abgeschlossen. Keine Daten wurden synchronisiert.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Fertig. Erfolgreich: {$okCount}, Fehler: {$failCount}");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
