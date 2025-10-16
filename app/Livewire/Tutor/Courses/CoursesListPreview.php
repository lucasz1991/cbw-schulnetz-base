<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class CoursesListPreview extends Component
{
    public $courses;

    public function mount(): void
    {
        $person = Auth::user()?->person; // Person-Model (nicht Relation)
        if (!$person) {
            $this->courses = collect();
            return;
        }

        $now = Carbon::now('Europe/Berlin');

        // Basisrelation: vom Dozent unterrichtete Kurse
        $base = $person->taughtCourses(); // ->belongsToMany(Course::class, ...) o.ä.

        // 1) Aktiver Kurs (falls vorhanden)
        $active = (clone $base)
            ->where('planned_start_date', '<=', $now)
            ->where('planned_end_date', '>=', $now)
            ->orderBy('planned_start_date', 'desc')
            ->limit(1)
            ->get();

        // 2) Zuletzt abgeschlossener Kurs
        $lastFinished = (clone $base)
            ->where('planned_end_date', '<', $now)
            ->orderBy('planned_end_date', 'desc')
            ->limit(1)
            ->get();

        // 3) Nächster bevorstehender Kurs
        $nextUpcoming = (clone $base)
            ->where('planned_start_date', '>', $now)
            ->orderBy('planned_start_date', 'asc')
            ->limit(1)
            ->get();

        // Zusammensetzen, Duplikate entfernen (zur Sicherheit)
        $this->courses = $active
            ->concat($lastFinished)
            ->concat($nextUpcoming)
            ->unique('id')
            ->values();
    }

    public function render()
    {
        return view('livewire.tutor.courses.courses-list-preview', [
            'courses' => $this->courses,
        ]);
    }
}
