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
     * Push + Pull für einen Kurs.
     *
     * - Push: nur CourseResults mit sync_state NULL oder 'dirty'
     * - Pull: übernimmt nur UVS-Werte, die "neuer" sind und lokale, nicht-dirty
     *         Ergebnisse nicht überschreiben.
     *
     * Die API-Seite sorgt dafür, dass fehlende tn_p_kla-Zeilen bei Bedarf
     * neu angelegt werden (INSERT mit Defaults).
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

            // Remote → lokal zurückschreiben (nur wenn "neu" für Schulnetz)
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
     * Liefert:
     *  - $changes Array für den UVS-Endpoint
     *  - $resultsForSync Collection der CourseResults, die tatsächlich rausgehen
     *
     * Es werden nur CourseResults mit:
     *  - sync_state NULL (nie gesynct)
     *  - oder sync_state = 'dirty'
     * berücksichtigt.
     *
     * Auf der API-Seite wird dann:
     *  - wenn passende tn_p_kla-Zeile existiert → UPDATE
     *  - sonst → INSERT mit Defaults + diesen Feldern
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

            $changes[] = [
                'teilnehmer_id'  => $teilnehmerId,
                'person_id'      => $personIdUvs,
                'institut_id'    => $institutId,
                'teilnehmer_fnr' => $teilnehmerFnr,

                // Ergebnis-Felder
                'status'         => $remoteStatus,
                'pruef_punkte'   => $remotePruefPunkte,
                'pruef_kennz'    => $remotePruefKennz,

                // Standard-Aktion: "update" – API entscheidet, ob UPDATE oder INSERT
                'action'         => 'update',

                // Zeitstempel als Info
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

    protected function mapLocalResultToPruefPunkte(mixed $result): int
    {
        if ($result === null || $result === '') {
            return 0;
        }

        if (! is_numeric($result)) {
            return 0;
        }

        $value = (int) round($result);

        return max(0, min(255, $value));
    }

    /**
     * Nach erfolgreichem Push: lokal als "synced" markieren.
     * remote_upd_date setzen wir vorläufig auf "jetzt";
     * genauer wird es, wenn später applySyncResponse die echten UVS-Daten bekommt.
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
     * UVS → Schulnetz: tn_p_kla nach CourseResult zurückschreiben
     *
     * Regeln:
     * - Wenn kein CourseResult existiert → anlegen (sync_state = remote)
     * - Wenn CourseResult.sync_state = dirty → NICHT überschreiben
     * - Sonst: nur überschreiben, wenn UVS-UpdDate > local.remote_upd_date
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

        // Wir mappen über teilnehmer_id → Person
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

            $remoteStatus     = isset($item['status'])       ? (int) $item['status']       : null;
            $remotePunkte     = isset($item['pruef_punkte']) ? (int) $item['pruef_punkte'] : null;
            $remotePruefKennz = $item['pruef_kennz'] ?? null;
            $remoteUid        = isset($item['uid']) ? (int) $item['uid'] : null;
            $remoteUpdRaw     = $item['upd_date'] ?? null; // Format: Y/m/d (UVS)

            $remoteUpdDate = null;
            if (is_string($remoteUpdRaw) && preg_match('#^\d{4}/\d{2}/\d{2}$#', $remoteUpdRaw)) {
                try {
                    $remoteUpdDate = Carbon::createFromFormat('Y/m/d', $remoteUpdRaw)->startOfDay();
                } catch (\Throwable $e) {
                    $remoteUpdDate = null;
                }
            }

            $localStatus = $this->mapRemoteStatusToLocalStatus($remoteStatus, $remotePruefKennz);
            $localResult = $this->mapRemotePunkteToLocalResult($remotePunkte);

            foreach ($persons[$teilnehmerId] as $person) {
                /** @var Person $person */

                $courseResult = CourseResult::firstOrNew([
                    'course_id' => $course->id,
                    'person_id' => $person->id,
                ]);

                // Neuer Datensatz → einfach übernehmen
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

                // Lokaler Datensatz ist "dirty" → niemals überschreiben
                if ($courseResult->sync_state === CourseResult::SYNC_STATE_DIRTY) {
                    $skippedDirty++;
                    continue;
                }

                // Prüfen, ob UVS wirklich "neuer" ist
                $localRemoteDate = $courseResult->remote_upd_date;

                $shouldOverwrite = false;

                if (! $localRemoteDate && $remoteUpdDate) {
                    // lokal kein Stand bekannt, remote hat Datum → übernehmen
                    $shouldOverwrite = true;
                } elseif (! $localRemoteDate && ! $remoteUpdDate) {
                    // beide ohne Datum → konservativ: übernehmen
                    $shouldOverwrite = true;
                } elseif ($remoteUpdDate && $localRemoteDate && $remoteUpdDate->gt($localRemoteDate)) {
                    // remote ist neuer
                    $shouldOverwrite = true;
                }

                if (! $shouldOverwrite) {
                    continue;
                }

                // Jetzt überschreiben wir lokal mit UVS
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

    protected function mapRemoteStatusToLocalStatus(?int $status, ?string $pruefKennz): ?string
    {
        if ($status === null) {
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
