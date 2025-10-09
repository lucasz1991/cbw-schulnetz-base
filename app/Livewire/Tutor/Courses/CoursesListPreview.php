<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class CoursesListPreview extends Component
{
    public $courses;

    public function mount(): void
    {
        $person = Auth::user()?->person; // <-- Model, nicht Relation

        $this->courses = $person
            ? $person->taughtCourses()   // Relation auf Person -> Course
                ->orderBy('planned_start_date', 'desc')
                ->take(8)
                ->get()
            : collect(); // falls (noch) keine Person verknüpft ist
    }

    public function render()
    {
        return view('livewire.tutor.courses.courses-list-preview', [
            'courses' => $this->courses,
        ]);
    }
}
