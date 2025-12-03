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
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function mount(Course $course): void
    {
        $this->course        = $course;
        $this->isExternalExam = (bool) $this->course->getSetting('isExternalExam', false);
        if(!$this->isExternalExam){
            $this->syncResults();
            $this->loadRows();
            $this->prefillResults();
        }
    }

    public function render(): View
    {
        return view('livewire.tutor.courses.manage-course-results');
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
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

        // Lokale Speicherung
        CourseResult::updateOrCreate(
            [
                'course_id' => $this->course->id,
                'person_id' => $personId,
            ],
            [
                'result'     => $value,
                'status'     => $status,
                'updated_by' => auth()->id(),
            ]
        );

        // ---- UVS SYNC DIREKT NACH JEDEM EINZELNEN SPEICHERN ----
        try {
            /** @var CourseResultsSyncService $syncService */
            $syncService = app(CourseResultsSyncService::class);
            $ok = $syncService->syncToRemote($this->course);

            if (! $ok) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: "UVS-Sync für Person #$personId fehlgeschlagen."
                );
            } else {
                // Nach Pull erneut Ergebnisse laden, falls UVS etwas geändert hat
                $this->prefillResults();
            }
        } catch (\Throwable $e) {
            Log::error('CourseResultsSyncService Fehler', [
                'course_id' => $this->course->id,
                'person_id' => $personId,
                'error'     => $e->getMessage(),
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

    public function syncResults(): void
    {
                // ---- UVS SYNC DIREKT NACH JEDEM EINZELNEN SPEICHERN ----
        try {
            /** @var CourseResultsSyncService $syncService */
            $syncService = app(CourseResultsSyncService::class);
            $ok = $syncService->syncToRemote($this->course);

            if (! $ok) {
                $this->dispatch(
                    'notify',
                    type: 'error',
                    message: "UVS-Sync für Person #$personId fehlgeschlagen."
                );
            } else {
                // Nach Pull erneut Ergebnisse laden, falls UVS etwas geändert hat
                $this->prefillResults();
            }
        } catch (\Throwable $e) {
            Log::error('CourseResultsSyncService Fehler', [
                'course_id' => $this->course->id,
                'person_id' => $personId,
                'error'     => $e->getMessage(),
            ]);

            $this->dispatch(
                'notify',
                type: 'error',
                message: "UVS-Sync Fehler für Person #$personId."
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
        if(! $value){
            $this->syncResults();
            $this->loadRows();
            $this->prefillResults();
        }
        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Prüfungsmodus aktualisiert.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                        <span class="loader"></span>
                        <span class="text-sm text-gray-700">wird geladen…</span>
                    </div>
                </div>
            </div>
        HTML;
    }
}
