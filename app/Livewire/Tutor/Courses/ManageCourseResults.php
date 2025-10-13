<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use App\Models\Course;
use App\Models\CourseResult; // s.u.
use Illuminate\Validation\Rule;

class ManageCourseResults extends Component
{
    public Course $course;

    /** @var array<int|string, array{name:string|null, result:string|null}> */
    public array $rows = [];

    /** Gebundene Werte: [person_id => result] */
    public array $results = [];

    /** UI */
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    public function mount(Course $course): void
    {
        $this->course = $course;
        $this->loadRows();
        $this->prefillResults();
    }

    public function render()
    {
        return view('livewire.tutor.courses.manage-course-results');
    }



    public function saveAll(): void
    {
        foreach (array_keys($this->rows) as $personId) {
            $this->saveOne((string)$personId, silent: true);
        }
        $this->dispatch('notify', type: 'success', message: 'Alle Ergebnisse gespeichert.');
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
        $this->prefillResults(); // Reihenfolge ändert sich, Werte bleiben
    }

    public function saveOne(string $personId, bool $silent = false): void
    {
        $this->validate([
            "results.$personId" => ['nullable', 'string', 'max:50'],
        ]);

        $value = $this->results[$personId] ?? null;

        \App\Models\CourseResult::updateOrCreate(
            ['course_id' => $this->course->id, 'person_id' => $personId],
            ['result' => $value, 'updated_by' => auth()->id()]
        );

        if (!$silent) {
            $this->dispatch('notify', type: 'success', message: "Gespeichert für Person #$personId.");
        }
    }

    private function loadRows(): void
    {
        // Beispiel: Teilnehmer-Relation am Course
        $participants = method_exists($this->course, 'participants')
            ? $this->course->participants()
                ->when($this->search, fn($q) =>
                    $q->where(fn($qq) =>
                        $qq->where('vorname','like','%'.$this->search.'%')
                        ->orWhere('nachname','like','%'.$this->search.'%')
                        ->orWhere('person_id','like','%'.$this->search.'%')
                    )
                )->get()
            : collect();

        $rows = [];
        foreach ($participants as $p) {
            $rows[(string)$p->id] = [
                'name' => trim(($p->vorname ?? '').' '.($p->nachname ?? '')) ?: ('Person #'.$p->id),
                'user' => $p, // <— wichtig für dein Blade
            ];
        }

        uasort($rows, function($a, $b) {
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
            $this->results = [];
            return;
        }

        $personIds = array_keys($this->rows);

        $existing = CourseResult::query()
            ->where('course_id', $this->course->id)
            ->whereIn('person_id', $personIds)
            ->get()
            ->keyBy(fn($r) => (string)$r->person_id);

        foreach ($this->rows as $pid => $_) {
            $this->results[$pid] = $existing[$pid]->result ?? null;
        }
    }

}
