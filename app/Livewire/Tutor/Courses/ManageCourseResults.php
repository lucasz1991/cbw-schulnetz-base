<?php

namespace App\Livewire\Tutor\Courses; 

use App\Models\Course;
use App\Models\CourseResult;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class ManageCourseResults extends Component
{
    public Course $course;

    public bool $isExternalExam = false;

    /** @var array<int|string, array{name: string|null, user: mixed}> */
    public array $rows = [];

    /** Gebundene Werte: [person_id => result] */
    public array $results = [];

    /** Gebundene Statuswerte: [person_id => status] */
    public array $statuses = [];

    /** UI */
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    /*
    |----------------------------------------------------------------------
    | Lifecycle
    |----------------------------------------------------------------------
    */

    public function mount(Course $course): void
    {
        $this->course         = $course;
        $this->isExternalExam = (bool) $this->course->getSetting('isExternalExam', false);

        // Beim Öffnen, wenn interne Prüfung: UVS ist Master → hart laden
        if (! $this->isExternalExam) {
            $this->performLoadFromRemote(silent: true);
        }

        $this->loadRows();
        $this->prefillResults();
        $this->normalizeStatusesForInitialRender();
    }

    public function render(): View
    {
        return view('livewire.tutor.courses.manage-course-results');
    }

    /*
    |----------------------------------------------------------------------
    | Actions
    |----------------------------------------------------------------------
    */

    /**
     * Einzelnes Ergebnis speichern + SYNC-Modus:
     * - lokal setzen (sync_state = DIRTY)
     * - danach syncToRemote() → UVS bekommt Änderungen,
     *   und evtl. Anpassungen aus UVS kommen zurück.
     */
    public function saveOne(string $personId, bool $silent = false): void
    {
        $this->validate([
            "results.$personId"  => ['nullable', 'numeric', 'min:0', 'max:100'],
            "statuses.$personId" => ['nullable', 'string', 'max:100'],
        ]);

        $syncService = $this->courseResultsSyncService();
        $value  = $this->results[$personId] ?? null;
        $status = $syncService->normalizeLocalStatus($this->statuses[$personId] ?? null, $value);
        $value  = $syncService->normalizeLocalResult($value, $status);

        $this->statuses[$personId] = $status;
        $this->results[$personId]  = $value;

        if ($this->courseResultsSyncService()->localStatusHasNoResult($status)) {
            $value = null;
            $this->results[$personId] = null;
        } else {
            // Status mit Ergebnis ist bereits oben normalisiert.
        }

        // Wenn Punkte gesetzt und kein Status → automatisch "An Prüfung teilgenommen"
        if ($value !== null && $value !== '' && ($status === null || $status === '')) {
            $status = '+';
            $this->statuses[$personId] = $status;
        }

        // Lokale Speicherung + als "DIRTY" markieren
        CourseResult::updateOrCreate(
            [
                'course_id' => $this->course->id,
                'person_id' => $personId,
            ],
            [
                'result'          => $value,
                'status'          => $status,
                'updated_by'      => auth()->id(),
                'sync_state'      => CourseResult::SYNC_STATE_DIRTY,
                'remote_upd_date' => null,
            ]
        );

        $syncOk = false;
        $syncFailMessage = "Lokal gespeichert, aber UVS-Sync fuer Person #$personId fehlgeschlagen.";

        try {
            // SYNC-Modus: nur DIRTY/unsynced Ergebnisse hochladen
            $syncOk = $syncService->syncToRemote($this->course);

            if ($syncOk) {
                // Nach Pull erneut Ergebnisse laden, falls UVS etwas angepasst hat
                $this->prefillResults();
            }
        } catch (\Throwable $e) {
            Log::error('CourseResultsSyncService Fehler im saveOne', [
                'course_id'   => $this->course->id,
                'person_id'   => $personId,
                'error'       => $e->getMessage(),
                'trace_short' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            $syncFailMessage = "Lokal gespeichert, aber UVS-Sync-Fehler fuer Person #$personId.";
            $syncOk = false;
        }

        if (! $syncOk) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: $syncFailMessage
            );

            return;
        }

        if (! $silent) {
            $this->dispatch(
                'notify',
                type: 'success',
                message: "Gespeichert für Person #$personId (inkl. UVS-Sync)."
            );
        }
    }

    public function setStatus(string $personId, ?string $status): void
    {
        $this->statuses[$personId] = $this->courseResultsSyncService()->normalizeLocalStatus(
            $status,
            $this->results[$personId] ?? null
        );
        $this->saveOne($personId, silent: true);
    }

    public function clearResult(string $personId): void
    {
        if (! $this->course->termin_id || ! $this->course->klassen_id) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Fehlende termin_id/klassen_id. Zuruecksetzen in UVS nicht moeglich.'
            );
            return;
        }

        $person = $this->course->participants()
            ->where('persons.id', $personId)
            ->first();

        if (! $person || ! $person->teilnehmer_id) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: "Teilnehmer #{$personId} konnte nicht fuer UVS-Zuruecksetzen aufgeloest werden."
            );
            return;
        }

        $payload = [
            'termin_id'      => (string) $this->course->termin_id,
            'klassen_id'     => (string) $this->course->klassen_id,
            'teilnehmer_ids' => [(string) $person->teilnehmer_id],
            'changes'        => [[
                'teilnehmer_id'  => (string) $person->teilnehmer_id,
                'person_id'      => (string) ($person->person_id ?? ''),
                'institut_id'    => (int) ($person->institut_id ?? $this->course->institut_id ?? 0),
                'teilnehmer_fnr' => (string) ($person->teilnehmer_fnr ?? '00'),
                'action'         => 'update',
                'status'         => 1,
                'pruef_punkte'   => null,
                'pruef_kennz'    => '',
                'aktiv'          => '',
            ]],
        ];

        try {
            /** @var ApiUvsService $api */
            $api = app(ApiUvsService::class);
            $response = $api->request('POST', '/api/course/courseresults/syncdata', $payload, []);

            $inner = [];
            if (is_array($response['data'] ?? null)) {
                $inner = $response['data']['data'] ?? $response['data'];
            }

            $pushedResults = (is_array($inner['pushed'] ?? null) && is_array($inner['pushed']['results'] ?? null))
                ? $inner['pushed']['results']
                : [];

            $remoteApplied = collect($pushedResults)
                ->filter(fn ($row) => is_array($row))
                ->contains(function (array $row) use ($person) {
                    $tid = (string) ($row['teilnehmer_id'] ?? '');
                    $action = strtolower(trim((string) ($row['action'] ?? '')));

                    return $tid === (string) $person->teilnehmer_id
                        && in_array($action, ['updated', 'inserted'], true);
                });

            $bodyNotOk = is_array($response['data'] ?? null)
                && array_key_exists('ok', $response['data'])
                && $response['data']['ok'] === false;

            if (empty($response['ok']) || $bodyNotOk || ! $remoteApplied) {
                Log::warning('ManageCourseResults.clearResult: Remote reset failed', [
                    'course_id' => $this->course->id,
                    'person_id' => $personId,
                    'payload' => $payload,
                    'response' => $response,
                    'remote_applied' => $remoteApplied,
                ]);

                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: "UVS-Zuruecksetzen fuer Person #{$personId} fehlgeschlagen."
                );
                return;
            }
        } catch (\Throwable $e) {
            Log::error('ManageCourseResults.clearResult exception', [
                'course_id' => $this->course->id,
                'person_id' => $personId,
                'error' => $e->getMessage(),
                'trace_short' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            $this->dispatch(
                'notify',
                type: 'error',
                message: "Fehler beim UVS-Zuruecksetzen fuer Person #{$personId}."
            );
            return;
        }

        CourseResult::query()
            ->where('course_id', $this->course->id)
            ->where('person_id', $personId)
            ->delete();

        $this->results[$personId] = null;
        $this->statuses[$personId] = null;

        $this->dispatch(
            'notify',
            type: 'success',
            message: "Ergebnis fuer Person #{$personId} wurde geloescht."
        );
    }
    /**
     * Manueller SYNC-Button:
     * - Lädt nur DIRTY/unsynced Einträge hoch (syncToRemote)
     * - Holt danach evtl. geänderte UVS-Werte zurück (SYNC-Modus).
     */
    public function syncResults(): void
    {
        try {
            $ok = $this->courseResultsSyncService()->syncToRemote($this->course);

            if (! $ok) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: 'UVS-Sync für diesen Kurs ist fehlgeschlagen.'
                );
            } else {
                $this->prefillResults();

                $this->dispatch(
                    'notify',
                    type: 'success',
                    message: 'Ergebnisse wurden mit UVS synchronisiert.'
                );
            }
        } catch (\Throwable $e) {
            Log::error('CourseResultsSyncService Fehler im syncResults', [
                'course_id'   => $this->course->id,
                'error'       => $e->getMessage(),
                'trace_short' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            $this->dispatch(
                'notify',
                type: 'error',
                message: 'UVS-Sync Fehler bei der Kurs-Synchronisation.'
            );
        }
    }

    /**
     * Optionaler Button in der UI möglich:
     * - UVS ist Master → ALLES hart aus UVS laden (LOAD-Modus).
     * - Überschreibt lokale CourseResults für diesen Kurs.
     */
    public function reloadFromUvs(): void
    {
        if ($this->isExternalExam) {
            // Bei externen Prüfungen kein automatischer Load
            return;
        }

        $this->performLoadFromRemote(silent: false);

        $this->prefillResults();

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Ergebnisse wurden vollständig aus UVS neu geladen.'
        );
    }

    /**
     * Debug: Loescht alle lokalen CourseResults dieses Kurses.
     */
    public function deleteLocalResults(): void
    {
        $deleted = CourseResult::query()
            ->where('course_id', $this->course->id)
            ->delete();

        $this->prefillResults();

        $this->dispatch(
            'notify',
            type: 'success',
            message: "Lokal geloescht: {$deleted} Ergebnis-Eintraege."
        );
    }

    /**
     * Debug: Versucht alle UVS-Ergebnisse fuer diesen Kurs zu loeschen
     * und loescht danach lokal.
     */
    public function deleteRemoteAndLocalResults(): void
    {
        if (! $this->course->termin_id || ! $this->course->klassen_id) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Fehlende termin_id/klassen_id. Remote-Loeschen nicht moeglich.'
            );
            return;
        }

        $participants = $this->course->participants()->get();

        $changes = [];
        foreach ($participants as $person) {
            if (! $person->teilnehmer_id) {
                continue;
            }

            $changes[] = [
                'teilnehmer_id'  => (string) $person->teilnehmer_id,
                'person_id'      => (string) ($person->person_id ?? ''),
                'institut_id'    => (int) ($person->institut_id ?? $this->course->institut_id ?? 0),
                'teilnehmer_fnr' => (string) ($person->teilnehmer_fnr ?? '00'),
                'action'         => 'delete',
            ];
        }

        if (empty($changes)) {
            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Keine gueltigen Teilnehmer fuer Remote-Loeschen gefunden.'
            );
            return;
        }

        $payload = [
            'termin_id'      => (string) $this->course->termin_id,
            'klassen_id'     => (string) $this->course->klassen_id,
            'teilnehmer_ids' => collect($changes)->pluck('teilnehmer_id')->unique()->values()->all(),
            'changes'        => $changes,
        ];

        try {
            /** @var ApiUvsService $api */
            $api = app(ApiUvsService::class);
            $response = $api->request('POST', '/api/course/courseresults/syncdata', $payload, []);

            if (empty($response['ok'])) {
                Log::warning('ManageCourseResults.deleteRemoteAndLocalResults: Remote delete failed', [
                    'course_id' => $this->course->id,
                    'response'  => $response,
                    'payload'   => $payload,
                ]);

                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: 'Remote-Loeschen fehlgeschlagen. Details im Log.'
                );
                return;
            }

            $deletedLocal = CourseResult::query()
                ->where('course_id', $this->course->id)
                ->delete();

            $this->prefillResults();

            $this->dispatch(
                'notify',
                type: 'success',
                message: "Remote geloescht (".count($changes)." Teilnehmer), lokal geloescht: {$deletedLocal}."
            );
        } catch (\Throwable $e) {
            Log::error('ManageCourseResults.deleteRemoteAndLocalResults exception', [
                'course_id'   => $this->course->id,
                'error'       => $e->getMessage(),
                'trace_short' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            $this->dispatch(
                'notify',
                type: 'error',
                message: 'Fehler beim Remote-/Lokal-Loeschen. Details im Log.'
            );
        }
    }

    public function sort(string $col): void
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $col;
            $this->sortDir = 'asc';
        }

        $this->loadRows();
        $this->prefillResults();
    }

    public function updatedIsExternalExam($value): void
    {
        $this->course->setSetting('isExternalExam', (bool) $value);
        $this->course->save();

        // Wenn von extern → intern gewechselt wird:
        // einmal hart aus UVS laden, damit wir mit dem UVS-Stand starten.
        if (! $value) {
            $this->performLoadFromRemote(silent: true);
        }

        $this->loadRows();
        $this->prefillResults();

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Prüfungsmodus aktualisiert.'
        );
    }

    /*
    |----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------
    */

    /**
     * UVS-LOAD-Modus:
     * - Ruft im Service loadFromRemote() auf.
     * - UVS überschreibt alle CourseResults des Kurses (Master).
     */
    private function performLoadFromRemote(bool $silent = false): void
    {
        try {
            $ok = $this->courseResultsSyncService()->loadFromRemote($this->course);

            if (! $ok && ! $silent) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: 'Ergebnisse konnten nicht aus UVS geladen werden.'
                );
            }

            if (! $ok) {
                Log::warning('CourseResultsSyncService.loadFromRemote meldet false', [
                    'course_id' => $this->course->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CourseResultsSyncService Fehler im performLoadFromRemote', [
                'course_id'   => $this->course->id,
                'error'       => $e->getMessage(),
                'trace_short' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            if (! $silent) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: 'Fehler beim Laden der Ergebnisse aus UVS.'
                );
            }
        }
    }

    private function loadRows(): void
    {
        $participants = method_exists($this->course, 'participants')
            ? $this->course->participants()
                ->when($this->search, fn ($q) =>
                    $q->where(fn ($qq) =>
                        $qq->where('vorname', 'like', '%' . $this->search . '%')
                           ->orWhere('nachname', 'like', '%' . $this->search . '%')
                           ->orWhere('person_id', 'like', '%' . $this->search . '%')
                    )
                )
                ->get()
            : collect();

        $rows = [];

        foreach ($participants as $p) {
            $rows[(string) $p->id] = [
                'name' => trim(($p->vorname ?? '') . ' ' . ($p->nachname ?? '')) ?: ('Person #' . $p->id),
                'user' => $p,
            ];
        }

        // Sortierung nach Name (asc/desc)
        uasort($rows, function ($a, $b) {
            $A = mb_strtolower($a['name'] ?? '');
            $B = mb_strtolower($b['name'] ?? '');
            $cmp = $A <=> $B;

            return $this->sortDir === 'asc' ? $cmp : -$cmp;
        });

        $this->rows = $rows;
    }

    private function prefillResults(): void
    {
        if (empty($this->rows)) {
            $this->results  = [];
            $this->statuses = [];

            return;
        }

        $personIds = array_keys($this->rows);

        $existing = CourseResult::query()
            ->where('course_id', $this->course->id)
            ->whereIn('person_id', $personIds)
            ->get()
            ->keyBy(fn ($r) => (string) $r->person_id);

        $syncService = $this->courseResultsSyncService();

        foreach ($this->rows as $pid => $_) {
            $storedResult = $existing[$pid]->result ?? null;
            $storedStatus = $existing[$pid]->status ?? null;
            $status       = $syncService->normalizeLocalStatus($storedStatus, $storedResult);

            $this->results[$pid]  = $syncService->normalizeLocalResult($storedResult, $status);
            $this->statuses[$pid] = $status;
        }
    }

    private function normalizeStatusesForInitialRender(): void
    {
        $syncService = $this->courseResultsSyncService();

        foreach ($this->rows as $pid => $_) {
            $status = $syncService->normalizeLocalStatus(
                $this->statuses[$pid] ?? null,
                $this->results[$pid] ?? null
            );

            $this->statuses[$pid] = $status;
            $this->results[$pid] = $syncService->normalizeLocalResult(
                $this->results[$pid] ?? null,
                $status
            );
        }
    } 

    private function normalizeStatusValue(?string $status, mixed $result): ?string
    {
        return $this->courseResultsSyncService()->normalizeLocalStatus($status, $result);
    }

    private function statusForcesZeroResult(?string $status): bool
    {
        return $this->courseResultsSyncService()->localStatusForcesZeroResult($status);
    }

    private function statusHasNoResult(?string $status): bool
    {
        return $this->courseResultsSyncService()->localStatusHasNoResult($status);
    }

    private function courseResultsSyncService(): CourseResultsSyncService
    {
        return app(CourseResultsSyncService::class);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                    <div class="flex items-center gap-3  px-4 py-2 ">
                        <span class="loader"></span>
                    </div>
                </div>
            </div>
        HTML;
    }
}

