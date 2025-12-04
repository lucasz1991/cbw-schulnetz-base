<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseResult;
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

        $value  = $this->results[$personId] ?? null;
        $status = $this->statuses[$personId] ?? null;

        // Wenn Punkte gesetzt und kein Status → automatisch "An Prüfung teilgenommen"
        if ($value !== null && $value !== '' && $status === null) {
            $status = 'An Prüfung teilgenommen';
            $this->statuses[$personId] = $status;
        }

        // Lokale Speicherung + als "DIRTY" markieren
        $courseResult = CourseResult::updateOrCreate(
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

        try {
            /** @var CourseResultsSyncService $syncService */
            $syncService = app(CourseResultsSyncService::class);

            // SYNC-Modus: nur DIRTY/unsynced Ergebnisse hochladen
            $ok = $syncService->syncToRemote($this->course);

            if (! $ok) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: "UVS-Sync für Person #$personId fehlgeschlagen."
                );
            } else {
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

            $this->dispatch(
                'notify',
                type: 'error',
                message: "UVS-Sync Fehler für Person #$personId."
            );
        }

        if (! $silent) {
            $this->dispatch(
                'notify',
                type: 'success',
                message: "Gespeichert für Person #$personId (inkl. UVS-Sync)."
            );
        }
    }

    /**
     * Manueller SYNC-Button:
     * - Lädt nur DIRTY/unsynced Einträge hoch (syncToRemote)
     * - Holt danach evtl. geänderte UVS-Werte zurück (SYNC-Modus).
     */
    public function syncResults(): void
    {
        try {
            /** @var CourseResultsSyncService $syncService */
            $syncService = app(CourseResultsSyncService::class);

            $ok = $syncService->syncToRemote($this->course);

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
            /** @var CourseResultsSyncService $syncService */
            $syncService = app(CourseResultsSyncService::class);

            $ok = $syncService->loadFromRemote($this->course);

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

        foreach ($this->rows as $pid => $_) {
            $this->results[$pid]  = $existing[$pid]->result ?? null;
            $this->statuses[$pid] = $existing[$pid]->status ?? null;
        }
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
