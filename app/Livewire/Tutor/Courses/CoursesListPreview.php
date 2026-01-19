<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class CoursesListPreview extends Component
{
    public bool $showAll = false; // <-- übergibt die Ansichtseinstellung
    public $courses;

    public function mount(bool $showAll = false): void
    {
        $this->showAll = $showAll;
        $person = Auth::user()?->person;

        if (!$person) {
            $this->courses = collect();
            return;
        }

        $now = Carbon::now('Europe/Berlin');
        $base = $person->taughtCourses(); // Relation

        if ($this->showAll) {
            // Zeige alle Kurse (z. B. absteigend nach Startdatum)
            $this->courses = $base
                ->where('planned_start_date', '>=', "2025-06-01")
                ->orderBy('planned_start_date', 'desc')
                ->get();
        } else {
            // Nur 3 relevante Kurse
            $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd   = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            // 1) Aktiver Kurs (inkl. Wochenend-Kulanz)
            $active = (clone $base)
                ->where('planned_start_date', '<=', $now)
                ->where(function ($q) use ($now, $weekStart, $weekEnd) {
                    $q->where('planned_end_date', '>=', $now)
                      ->orWhereBetween('planned_end_date', [$weekStart, $weekEnd]);
                })
                ->orderBy('planned_start_date', 'desc')
                ->limit(1)
                ->get();

            // 2) Zuletzt abgeschlossener Kurs
            $lastFinished = (clone $base)
                ->where('planned_end_date', '<', $now)
                ->orderBy('planned_end_date', 'desc')
                ->limit(1)
                ->get();

            // 3) Nächster Kurs
            $nextUpcoming = (clone $base)
                ->where('planned_start_date', '>', $now)
                ->orderBy('planned_start_date', 'asc')
                ->limit(1)
                ->get();

            $this->courses = $active
                ->concat($nextUpcoming)
                ->concat($lastFinished)
                ->unique('id')
                ->values();
        }
    }

    public function render()
    {
        return view('livewire.tutor.courses.courses-list-preview', [
            'courses' => $this->courses,
        ]);
    }
}
