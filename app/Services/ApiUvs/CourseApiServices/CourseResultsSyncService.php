<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\Course;
use App\Models\CourseResult;
use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CourseResultsSyncService
{
    protected ApiUvsService $api;

    public function __construct(ApiUvsService $api)
    {
        $this->api = $api;
    }

    /**
     * SYNC-Modus:
     * - Push: nur CourseResults mit sync_state NULL oder 'dirty'
     * - Pull: übernimmt nur UVS-Werte, die "neuer" sind und lokale, nicht-dirty
     *         Ergebnisse nicht überschreiben.
     *
     * Wird z.B. nach dem Speichern des Dozentenformulars verwendet.
     */
    public function syncToRemote(Course $course): bool
    {
        if (! $course->termin_id || ! $course->klassen_id) {
            Log::warning('CourseResultsSyncService.syncToRemote: fehlende termin_id/klassen_id.', [
                'course_id'  => $course->id,
                'termin_id'  => $course->termin_id,
                'klassen_id' => $course->klassen_id,
            ]);

            return false;
        }

        // 1) Lokale Ergebnisse, die überhaupt gesynct werden sollen
        [$changes, $resultsForSync] = $this->mapResultsToUvsChanges($course);

        // 2) Relevante Teilnehmer-IDs für Pull
        $teilnehmerIds = $this->collectTeilnehmerIds($course);

        if (empty($teilnehmerIds) && empty($changes)) {
            // Nichts zu tun
            return true;
        }

        $payload = [
            'termin_id'      => (string) $course->termin_id,
            'klassen_id'     => (string) $course->klassen_id,
            'teilnehmer_ids' => $teilnehmerIds,
            'changes'        => $changes,
        ];

        $response = $this->api->request(
            'POST',
            '/api/course/courseresults/syncdata',
            $payload,
            []
        );

        if (! empty($response['ok'])) {
            // Alle erfolgreich rausgeschickten lokal als "synced" markieren
            $this->markResultsSynced($resultsForSync);

            // Remote → lokal zurückschreiben (UVS gewinnt nur, wenn "neuer" und nicht dirty)
            $this->applySyncResponse($course, $response);

            Log::info('CourseResultsSyncService.syncToRemote: Sync OK.', [
                'course_id' => $course->id,
                'changes'   => count($changes),
            ]);

            return true;
        }

        Log::error('CourseResultsSyncService.syncToRemote: UVS-Response nicht ok.', [
            'course_id' => $course->id,
            'response'  => $response,
        ]);

        return false;
    }

    /**
     * LOAD-Modus:
     *
     * - Wird beim Öffnen des Dozenten-Ergebnis-Formulars verwendet.
     * - Es werden KEINE lokalen Änderungen hochgeladen.
     * - Alle CourseResults des Kurses werden "hart" mit den UVS-Werten
     *   überschrieben (UVS ist Master).
     */
    public function loadFromRemote(Course $course): bool
    {
        if (! $course->termin_id || ! $course->klassen_id) {
            Log::warning('CourseResultsSyncService.loadFromRemote: fehlende termin_id/klassen_id.', [
                'course_id'  => $course->id,
                'termin_id'  => $course->termin_id,
                'klassen_id' => $course->klassen_id,
            ]);

            return false;
        }

        $teilnehmerIds = $this->collectTeilnehmerIds($course);

        if (empty($teilnehmerIds)) {
            // Keine Teilnehmer → nichts zu laden
            return true;
        }

        $payload = [
            'termin_id'      => (string) $course->termin_id,
            'klassen_id'     => (string) $course->klassen_id,
            'teilnehmer_ids' => $teilnehmerIds,
        ];

        $response = $this->api->request(
            'POST',
            '/api/course/courseresults/loaddata',
            $payload,
            []
        );

        if (! empty($response['ok'])) {
            // Remote → lokal: UVS überschreibt ALLE lokalen CourseResults dieses Kurses
            $this->applyLoadResponse($course, $response);

            Log::info('CourseResultsSyncService.loadFromRemote: Load OK.', [
                'course_id' => $course->id,
            ]);

            return true;
        }

        Log::error('CourseResultsSyncService.loadFromRemote: UVS-Response nicht ok.', [
            'course_id' => $course->id,
            'response'  => $response,
        ]);

        return false;
    }

    /**
     * Alle teilnehmer_id der Kurs-Teilnehmer sammeln.
     */
    protected function collectTeilnehmerIds(Course $course): array
    {
        $participants = $course->participants ?? collect();

        if ($participants->isEmpty()) {
            return [];
        }

        return $participants
            ->map(fn ($person) => (string) $person->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Für SYNC: nur dirty/unsynced CourseResults → UVS changes.
     */
    protected function mapResultsToUvsChanges(Course $course): array
    {
        $results = CourseResult::where('course_id', $course->id)
            ->where(function ($q) {
                $q->whereNull('sync_state')
                  ->orWhere('sync_state', CourseResult::SYNC_STATE_DIRTY);
            })
            ->get();

        if ($results->isEmpty()) {
            return [[], collect()];
        }

        $personIds = $results->pluck('person_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($personIds)) {
            return [[], collect()];
        }

        $persons = Person::whereIn('id', $personIds)->get()->keyBy('id');

        $now           = Carbon::now();
        $changes       = [];
        $syncedResults = collect();

        foreach ($results as $result) {
            /** @var CourseResult $result */
            $person = $persons->get($result->person_id);

            if (! $person || ! $person->teilnehmer_id) {
                continue;
            }

            $teilnehmerId  = (string) $person->teilnehmer_id;
            $personIdUvs   = (string) ($person->person_id ?? '');
            $institutId    = (int) ($person->institut_id ?? $course->institut_id ?? 0);
            $teilnehmerFnr = (string) ($person->teilnehmer_fnr ?? '00');

            $remoteStatus      = $this->mapLocalStatusToRemoteStatus($result->status);
            $remotePruefKennz  = $this->mapLocalStatusToPruefKennz($result->status);
            $remotePruefPunkte = $this->mapLocalResultToPruefPunkte($result->result);

            // Leere Datensaetze nicht als 0/0 nach UVS pushen.
            if ($remoteStatus === 0 && $remotePruefPunkte === null && $remotePruefKennz === '') {
                continue;
            }

            $changes[] = [
                'teilnehmer_id'  => $teilnehmerId,
                'person_id'      => $personIdUvs,
                'institut_id'    => $institutId,
                'teilnehmer_fnr' => $teilnehmerFnr,

                'status'         => $remoteStatus,
                'pruef_punkte'   => $remotePruefPunkte,
                'pruef_kennz'    => $remotePruefKennz,

                'action'         => 'update',
                'updated_at'     => ($result->updated_at ?? $now)->toIso8601String(),
            ];

            $syncedResults->push($result);
        }

        return [$changes, $syncedResults];
    }

    protected function mapLocalStatusToRemoteStatus(?string $status): int
    {
        if ($status === null || $status === '') {
            return 0;
        }

        $status = strtolower($status);

        return match ($status) {
            'passed', 'bestanden'        => 1,
            'failed', 'nicht_bestanden'  => 2,
            'not_participated', 'nt',
            'nicht_teilgenommen'         => 3,
            default                      => is_numeric($status) ? (int) $status : 0,
        };
    }

    protected function mapLocalStatusToPruefKennz(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $status = strtolower($status);

        return match ($status) {
            'passed', 'bestanden'        => 'P',
            'failed', 'nicht_bestanden'  => 'F',
            'not_participated', 'nt',
            'nicht_teilgenommen'         => 'NT',
            default                      => strtoupper(substr($status, 0, 3)),
        };
    }

    protected function mapLocalResultToPruefPunkte(mixed $result): ?int
    {
        if ($result === null || $result === '') {
            return null;
        }

        if (! is_numeric($result)) {
            return null;
        }

        $value = (int) round($result);

        return max(0, min(255, $value));
    }

    protected function parseNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function hasMeaningfulRemoteExamData(?int $status, ?int $punkte, ?string $pruefKennz): bool
    {
        $kennz = is_string($pruefKennz) ? trim($pruefKennz) : '';

        // Typischer "kein Ergebnis vorhanden"-Fall aus UVS.
        if (($status === null || $status === 0) && ($punkte === null || $punkte === 0) && $kennz === '') {
            return false;
        }

        return true;
    }

    /**
     * Nach erfolgreichem PUSH im SYNC-Modus: lokal als "synced" markieren.
     */
    protected function markResultsSynced(Collection $results): void
    {
        if ($results->isEmpty()) {
            return;
        }

        $now = Carbon::now()->startOfDay();

        foreach ($results as $result) {
            /** @var CourseResult $result */
            $result->remote_upd_date = $now;
            $result->sync_state      = CourseResult::SYNC_STATE_SYNCED;
            $result->saveQuietly();
        }
    }

    /**
     * SYNC-Modus: UVS-Daten zurückschreiben, aber nur wenn UVS "neuer" ist
     * und das lokale Ergebnis NICHT dirty ist.
     */
    protected function applySyncResponse(Course $course, array $response): void
    {
        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pulled  = $innerData['pulled'] ?? null;
        $items   = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];

        if (empty($items)) {
            return;
        }

        $teilnehmerIds = collect($items)
            ->pluck('teilnehmer_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($teilnehmerIds)) {
            return;
        }

        $persons = Person::whereIn('teilnehmer_id', $teilnehmerIds)
            ->get()
            ->groupBy('teilnehmer_id');

        $updatedCount = 0;
        $createdCount = 0;
        $skippedDirty = 0;

        foreach ($items as $item) {
            $teilnehmerId = $item['teilnehmer_id'] ?? null;
            if (! $teilnehmerId || empty($persons[$teilnehmerId])) {
                continue;
            }

            $remoteStatus     = $this->parseNullableInt($item['status'] ?? null);
            $remotePunkte     = $this->parseNullableInt($item['pruef_punkte'] ?? null);
            $remotePruefKennz = $item['pruef_kennz'] ?? null;
            $remoteUid        = isset($item['uid']) ? (int) $item['uid'] : null;
            $remoteUpdRaw     = $item['upd_date'] ?? null; // Y/m/d

            $remoteUpdDate = null;
            if (is_string($remoteUpdRaw) && preg_match('#^\d{4}/\d{2}/\d{2}$#', $remoteUpdRaw)) {
                try {
                    $remoteUpdDate = Carbon::createFromFormat('Y/m/d', $remoteUpdRaw)->startOfDay();
                } catch (\Throwable $e) {
                    $remoteUpdDate = null;
                }
            }

            if (! $this->hasMeaningfulRemoteExamData($remoteStatus, $remotePunkte, $remotePruefKennz)) {
                continue;
            }

            $localStatus = $this->mapRemoteStatusToLocalStatus($remoteStatus, $remotePruefKennz);
            $localResult = $this->mapRemotePunkteToLocalResult($remotePunkte);

            foreach ($persons[$teilnehmerId] as $person) {
                /** @var Person $person */

                $courseResult = CourseResult::firstOrNew([
                    'course_id' => $course->id,
                    'person_id' => $person->id,
                ]);

                // Neu → direkt übernehmen
                if (! $courseResult->exists) {
                    $courseResult->result          = $localResult;
                    $courseResult->status          = $localStatus;
                    $courseResult->remote_uid      = $remoteUid;
                    $courseResult->remote_upd_date = $remoteUpdDate;
                    $courseResult->sync_state      = CourseResult::SYNC_STATE_REMOTE;

                    $courseResult->saveQuietly();
                    $createdCount++;

                    continue;
                }

                // Dirty bleibt unangetastet
                if ($courseResult->sync_state === CourseResult::SYNC_STATE_DIRTY) {
                    $skippedDirty++;
                    continue;
                }

                $localRemoteDate = $courseResult->remote_upd_date;
                $shouldOverwrite = false;

                if (! $localRemoteDate && $remoteUpdDate) {
                    $shouldOverwrite = true;
                } elseif (! $localRemoteDate && ! $remoteUpdDate) {
                    $shouldOverwrite = true;
                } elseif ($remoteUpdDate && $localRemoteDate && $remoteUpdDate->gt($localRemoteDate)) {
                    $shouldOverwrite = true;
                }

                if (! $shouldOverwrite) {
                    continue;
                }

                $courseResult->result          = $localResult;
                $courseResult->status          = $localStatus;
                $courseResult->remote_uid      = $remoteUid;
                $courseResult->remote_upd_date = $remoteUpdDate;
                $courseResult->sync_state      = CourseResult::SYNC_STATE_SYNCED;

                $courseResult->saveQuietly();
                $updatedCount++;
            }
        }

        Log::info('CourseResultsSyncService.applySyncResponse: CourseResults aus UVS übernommen.', [
            'course_id'    => $course->id,
            'created'      => $createdCount,
            'updated'      => $updatedCount,
            'skippedDirty' => $skippedDirty,
            'items_total'  => count($items),
        ]);
    }

    /**
     * LOAD-Modus:
     * UVS → Schulnetz "hart":
     * - Für alle teilnehmer_id, die UVS liefert, werden CourseResults angelegt/geupdatet.
     * - sync_state wird NICHT berücksichtigt, UVS überschreibt alles.
     * - remote_upd_date wird aus upd_date gesetzt.
     */
    protected function applyLoadResponse(Course $course, array $response): void
    {
        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pulled  = $innerData['pulled'] ?? null;
        $items   = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];

        if (empty($items)) {
            return;
        }

        $teilnehmerIds = collect($items)
            ->pluck('teilnehmer_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($teilnehmerIds)) {
            return;
        }

        $persons = Person::whereIn('teilnehmer_id', $teilnehmerIds)
            ->get()
            ->groupBy('teilnehmer_id');

        $updatedCount = 0;
        $createdCount = 0;

        foreach ($items as $item) {
            $teilnehmerId = $item['teilnehmer_id'] ?? null;
            if (! $teilnehmerId || empty($persons[$teilnehmerId])) {
                continue;
            }

            $remoteStatus     = $this->parseNullableInt($item['status'] ?? null);
            $remotePunkte     = $this->parseNullableInt($item['pruef_punkte'] ?? null);
            $remotePruefKennz = $item['pruef_kennz'] ?? null;
            $remoteUid        = isset($item['uid']) ? (int) $item['uid'] : null;
            $remoteUpdRaw     = $item['upd_date'] ?? null; // Y/m/d

            $remoteUpdDate = null;
            if (is_string($remoteUpdRaw) && preg_match('#^\d{4}/\d{2}/\d{2}$#', $remoteUpdRaw)) {
                try {
                    $remoteUpdDate = Carbon::createFromFormat('Y/m/d', $remoteUpdRaw)->startOfDay();
                } catch (\Throwable $e) {
                    $remoteUpdDate = null;
                }
            }

            $hasRemoteData = $this->hasMeaningfulRemoteExamData($remoteStatus, $remotePunkte, $remotePruefKennz);
            $localStatus = $this->mapRemoteStatusToLocalStatus($remoteStatus, $remotePruefKennz);
            $localResult = $this->mapRemotePunkteToLocalResult($remotePunkte);

            foreach ($persons[$teilnehmerId] as $person) {
                /** @var Person $person */

                $courseResult = CourseResult::firstOrNew([
                    'course_id' => $course->id,
                    'person_id' => $person->id,
                ]);

                // Im Load-Modus sollen "keine Ergebnisdaten" nicht als 0 gespeichert werden.
                // Existierende lokale Daten werden dabei auf null zurueckgesetzt.
                if (! $hasRemoteData) {
                    if ($courseResult->exists) {
                        $courseResult->result          = null;
                        $courseResult->status          = null;
                        $courseResult->remote_uid      = $remoteUid;
                        $courseResult->remote_upd_date = $remoteUpdDate;
                        $courseResult->sync_state      = CourseResult::SYNC_STATE_SYNCED;
                        $courseResult->saveQuietly();
                        $updatedCount++;
                    }
                    continue;
                }

                $courseResult->result          = $localResult;
                $courseResult->status          = $localStatus;
                $courseResult->remote_uid      = $remoteUid;
                $courseResult->remote_upd_date = $remoteUpdDate;
                $courseResult->sync_state      = $courseResult->exists
                    ? CourseResult::SYNC_STATE_SYNCED
                    : CourseResult::SYNC_STATE_REMOTE;

                $courseResult->saveQuietly();

                if ($courseResult->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }
        }

        Log::info('CourseResultsSyncService.applyLoadResponse: CourseResults aus UVS (hart) übernommen.', [
            'course_id'   => $course->id,
            'created'     => $createdCount,
            'updated'     => $updatedCount,
            'items_total' => count($items),
        ]);
    }

    protected function mapRemoteStatusToLocalStatus(?int $status, ?string $pruefKennz): ?string
    {
        if ($status === null) {
            return null;
        }

        if ($status === 0 && (! is_string($pruefKennz) || trim($pruefKennz) === '')) {
            return null;
        }

        return match ($status) {
            1       => 'bestanden',
            2       => 'nicht_bestanden',
            3       => 'nicht_teilgenommen',
            default => (string) $status,
        };
    }

    protected function mapRemotePunkteToLocalResult(?int $punkte): ?int
    {
        if ($punkte === null) {
            return null;
        }

        return $punkte;
    }
}
