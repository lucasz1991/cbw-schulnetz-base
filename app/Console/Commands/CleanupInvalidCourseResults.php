<?php

namespace App\Console\Commands;

use App\Models\CourseResult;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CleanupInvalidCourseResults extends Command
{
    protected $signature = 'course-results:cleanup-invalid-passed-zero
                            {--course-id= : Nur einen Kurs (course_id) verarbeiten}
                            {--dry-run : Nur anzeigen, nichts loeschen}';

    protected $description = 'Loescht fehlerhafte CourseResults (status=bestanden + result=0) lokal und in UVS.';

    public function handle(ApiUvsService $api): int
    {
        $courseId = $this->option('course-id');
        $dryRun = (bool) $this->option('dry-run');

        $query = CourseResult::query()->with(['course', 'person']);

        if ($courseId !== null && $courseId !== '') {
            $query->where('course_id', (int) $courseId);
        }

        $candidates = $query->get()->filter(fn (CourseResult $r) => $this->isInvalidResult($r));

        if ($candidates->isEmpty()) {
            $this->info('Keine fehlerhaften CourseResults gefunden.');
            return self::SUCCESS;
        }

        $this->warn('Gefundene fehlerhafte Eintraege: ' . $candidates->count());

        $groups = $candidates->groupBy('course_id');
        $deletedLocal = 0;
        $deletedRemoteCourses = 0;
        $failedCourses = 0;

        foreach ($groups as $groupCourseId => $results) {
            /** @var Collection<int, CourseResult> $results */
            $course = $results->first()?->course;
            $count = $results->count();

            if (! $course) {
                $this->error("Kurs #{$groupCourseId}: Course fehlt, uebersprungen.");
                $failedCourses++;
                continue;
            }

            $this->line("Kurs #{$groupCourseId}: {$count} lokale fehlerhafte Eintraege.");

            if (empty($course->termin_id) || empty($course->klassen_id)) {
                $this->error("Kurs #{$groupCourseId}: termin_id/klassen_id fehlt, uebersprungen.");
                $failedCourses++;
                continue;
            }

            $changes = $results
                ->map(function (CourseResult $result) use ($course) {
                    $person = $result->person;
                    if (! $person || ! $person->teilnehmer_id) {
                        return null;
                    }

                    return [
                        'teilnehmer_id'  => (string) $person->teilnehmer_id,
                        'person_id'      => (string) ($person->person_id ?? ''),
                        'institut_id'    => (int) ($person->institut_id ?? $course->institut_id ?? 0),
                        'teilnehmer_fnr' => (string) ($person->teilnehmer_fnr ?? '00'),
                        'action'         => 'delete',
                    ];
                })
                ->filter()
                ->unique('teilnehmer_id')
                ->values();

            if ($changes->isEmpty()) {
                $this->error("Kurs #{$groupCourseId}: Keine UVS-loeschbaren Teilnehmer gefunden.");
                $failedCourses++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] UVS delete fuer {$changes->count()} Teilnehmer und lokales Delete von {$count} Eintraegen.");
                continue;
            }

            $payload = [
                'termin_id'      => (string) $course->termin_id,
                'klassen_id'     => (string) $course->klassen_id,
                'teilnehmer_ids' => $changes->pluck('teilnehmer_id')->all(),
                'changes'        => $changes->all(),
            ];

            $response = $api->request(
                'POST',
                '/api/course/courseresults/syncdata',
                $payload,
                []
            );

            if (empty($response['ok'])) {
                $this->error("  UVS delete fehlgeschlagen (Kurs #{$groupCourseId}). Lokales Delete nicht ausgefuehrt.");
                Log::warning('CleanupInvalidCourseResults: UVS delete failed', [
                    'course_id' => $groupCourseId,
                    'payload'   => $payload,
                    'response'  => $response,
                ]);
                $failedCourses++;
                continue;
            }

            $deleted = CourseResult::query()
                ->whereIn('id', $results->pluck('id')->all())
                ->delete();

            $deletedLocal += $deleted;
            $deletedRemoteCourses++;

            $this->info("  OK: UVS geloescht, lokal geloescht: {$deleted}.");
        }

        if ($dryRun) {
            $this->warn('Dry-run abgeschlossen. Keine Daten veraendert.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Fertig. Kurse remote bearbeitet: {$deletedRemoteCourses}, lokal geloescht: {$deletedLocal}, Fehlerkurse: {$failedCourses}");

        return $failedCourses > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isInvalidResult(CourseResult $result): bool
    {
        $status = is_string($result->status)
            ? mb_strtolower(trim($result->status))
            : '';

        if ($status !== 'bestanden') {
            return false;
        }

        $value = $result->result;

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return false;
            }
        }

        return is_numeric($value) && (float) $value == 0.0;
    }
}
