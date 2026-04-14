<?php

namespace App\Console\Commands;

use App\Models\CourseResult;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BackfillNotParticipatedCourseResults extends Command
{
    protected $signature = 'course-results:backfill-not-participated-zero-points
                            {--course-id= : Nur einen Kurs (course_id) verarbeiten}
                            {--dry-run : Nur anzeigen, nichts speichern oder synchronisieren}';

    protected $description = 'Setzt bei CourseResults mit Status "nicht teilgenommen" lokal 0 Punkte und synchronisiert diese Aenderung nach UVS.';

    public function handle(CourseResultsSyncService $syncService): int
    {
        $courseId = $this->option('course-id');
        $dryRun = (bool) $this->option('dry-run');

        $query = CourseResult::query()
            ->with(['course', 'person'])
            ->orderBy('course_id')
            ->orderBy('id');

        if ($courseId !== null && $courseId !== '') {
            $query->where('course_id', (int) $courseId);
        }

        $candidates = $query->get()
            ->filter(fn (CourseResult $result) => $syncService->isLocalStatusNotParticipated($result->status))
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('Keine CourseResults mit Status "nicht teilgenommen" gefunden.');

            return self::SUCCESS;
        }

        $this->info('Gefundene Eintraege mit Status "nicht teilgenommen": ' . $candidates->count());

        $courseCount = 0;
        $syncedCourses = 0;
        $failedCourses = 0;
        $locallyAdjusted = 0;
        $markedDirty = 0;
        $unmappableEntries = 0;

        foreach ($candidates->groupBy('course_id') as $groupCourseId => $results) {
            /** @var Collection<int, CourseResult> $results */
            $course = $results->first()?->course;

            if (! $course) {
                $this->error("Kurs #{$groupCourseId}: Course fehlt, uebersprungen.");
                $failedCourses++;
                continue;
            }

            $courseCount++;

            $syncableResults = collect();
            $courseAdjusted = 0;
            $courseMarkedDirty = 0;
            $courseUnmappable = 0;

            foreach ($results as $result) {
                $normalizedStatus = $syncService->normalizeLocalStatus($result->status, $result->result);
                $normalizedResult = $syncService->normalizeLocalResult($result->result, $normalizedStatus);

                $statusChanged = (string) ($result->status ?? '') !== (string) ($normalizedStatus ?? '');
                $resultChanged = (string) ($result->result ?? '') !== (string) ($normalizedResult ?? '');
                $needsDirtyMark = $result->sync_state !== CourseResult::SYNC_STATE_DIRTY || $result->remote_upd_date !== null;

                if ($statusChanged || $resultChanged) {
                    $courseAdjusted++;
                }

                if ($needsDirtyMark) {
                    $courseMarkedDirty++;
                }

                if (! $dryRun) {
                    $result->status = $normalizedStatus;
                    $result->result = $normalizedResult;
                    $result->sync_state = CourseResult::SYNC_STATE_DIRTY;
                    $result->remote_upd_date = null;
                    $result->saveQuietly();
                }

                if (! $result->person || ! $result->person->teilnehmer_id) {
                    $courseUnmappable++;
                    continue;
                }

                $syncableResults->push($result);
            }

            $locallyAdjusted += $courseAdjusted;
            $markedDirty += $courseMarkedDirty;
            $unmappableEntries += $courseUnmappable;

            $label = sprintf(
                'Kurs #%d (klassen_id=%s, termin_id=%s)',
                $course->id,
                (string) ($course->klassen_id ?? 'n/a'),
                (string) ($course->termin_id ?? 'n/a')
            );

            if ($dryRun) {
                $this->line("[dry-run] {$label} -> kandidaten={$results->count()}, lokal_korrigiert={$courseAdjusted}, fuer_sync_markiert={$courseMarkedDirty}, ohne_teilnehmer_id={$courseUnmappable}");
                continue;
            }

            if ($syncableResults->isEmpty()) {
                $this->error("FEHLER: {$label}, keine syncbaren Teilnehmer vorhanden.");
                $failedCourses++;
                continue;
            }

            if (empty($course->termin_id) || empty($course->klassen_id)) {
                $this->error("FEHLER: {$label}, termin_id/klassen_id fehlt.");
                $failedCourses++;
                continue;
            }

            $ok = $syncService->syncToRemote($course, $syncableResults);

            if ($ok) {
                $syncedCourses++;
                $this->info("OK: {$label}, synchronisiert={$syncableResults->count()}, lokal_korrigiert={$courseAdjusted}, ohne_teilnehmer_id={$courseUnmappable}");
                continue;
            }

            $failedCourses++;
            $this->error("FEHLER: {$label}, Sync zur UVS fehlgeschlagen.");

            Log::warning('BackfillNotParticipatedCourseResults: sync failed.', [
                'course_id' => $course->id,
                'klassen_id' => $course->klassen_id,
                'termin_id' => $course->termin_id,
                'results_count' => $results->count(),
                'syncable_results_count' => $syncableResults->count(),
                'course_adjusted' => $courseAdjusted,
                'course_unmappable' => $courseUnmappable,
            ]);
        }

        if ($dryRun) {
            $this->warn("Dry-run abgeschlossen. Kurse={$courseCount}, lokal_korrigiert={$locallyAdjusted}, fuer_sync_markiert={$markedDirty}, ohne_teilnehmer_id={$unmappableEntries}");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Fertig. Kurse={$courseCount}, erfolgreich synchronisiert={$syncedCourses}, Fehlerkurse={$failedCourses}, lokal_korrigiert={$locallyAdjusted}, fuer_sync_markiert={$markedDirty}, ohne_teilnehmer_id={$unmappableEntries}");

        return $failedCourses > 0 ? self::FAILURE : self::SUCCESS;
    }
}
