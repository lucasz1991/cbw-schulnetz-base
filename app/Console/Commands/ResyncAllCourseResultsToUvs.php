<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseResult;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResyncAllCourseResultsToUvs extends Command
{
    protected $signature = 'course-results:resync-all-uvs
                            {--course-id= : Nur einen Kurs (course_id) verarbeiten}
                            {--dry-run : Nur anzeigen, nichts synchronisieren}';

    protected $description = 'Markiert lokale CourseResults als dirty und synchronisiert sie vollstaendig zur UVS-API.';

    public function handle(CourseResultsSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $courseIdOption = $this->option('course-id');
        $debugLogs = (bool) config('api_sync.debug_logs', false);

        $courseIdsWithResults = CourseResult::query()
            ->select('course_id')
            ->whereNotNull('course_id')
            ->distinct()
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($courseIdsWithResults)) {
            $this->info('Keine lokalen CourseResults gefunden.');
            return self::SUCCESS;
        }

        $query = Course::query()
            ->whereIn('id', $courseIdsWithResults)
            ->whereNotNull('termin_id')
            ->whereNotNull('klassen_id')
            ->withCount('results')
            ->orderBy('id');

        if ($courseIdOption !== null && $courseIdOption !== '') {
            $query->where('id', (int) $courseIdOption);
        }

        $courses = $query->get();

        if ($courses->isEmpty()) {
            $this->warn('Keine sync-faehigen Kurse gefunden (termin_id/klassen_id fehlen oder keine Results).');
            return self::SUCCESS;
        }

        $this->info('Gefundene Kurse fuer Re-Sync: ' . $courses->count());

        $okCount = 0;
        $failCount = 0;
        $dirtyMarked = 0;

        foreach ($courses as $course) {
            $label = sprintf(
                '#%d (klassen_id=%s, termin_id=%s, results=%d)',
                $course->id,
                (string) ($course->klassen_id ?? 'n/a'),
                (string) ($course->termin_id ?? 'n/a'),
                (int) ($course->results_count ?? 0)
            );

            $affected = CourseResult::query()
                ->where('course_id', $course->id)
                ->update([
                    'sync_state' => CourseResult::SYNC_STATE_DIRTY,
                    'remote_upd_date' => null,
                ]);

            $dirtyMarked += $affected;

            if ($dryRun) {
                $this->line("[dry-run] {$label} -> dirty_marked={$affected}");
                continue;
            }

            $ok = $syncService->syncToRemote($course);

            if ($ok) {
                $okCount++;
                $this->info("OK: {$label}, dirty_marked={$affected}");
            } else {
                $failCount++;
                $this->error("FEHLER: {$label}, dirty_marked={$affected}");

                if ($debugLogs) {
                    Log::warning('ResyncAllCourseResultsToUvs: course sync failed.', [
                        'course_id' => $course->id,
                        'klassen_id' => $course->klassen_id,
                        'termin_id' => $course->termin_id,
                        'results_count' => (int) ($course->results_count ?? 0),
                    ]);
                }
            }
        }

        if ($dryRun) {
            $this->warn("Dry-run abgeschlossen. Markierbar als dirty: {$dirtyMarked}. Keine Sync-Requests gesendet.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Fertig. Erfolgreich: {$okCount}, Fehler: {$failCount}, als dirty markiert: {$dirtyMarked}");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}

