<?php

namespace App\Livewire\User\Program\Course;

use App\Models\Course;
use App\Models\File;
use Livewire\Component;

class CourseShowMedia extends Component
{
    public Course $course;
    public bool $openPreview = false;

    public function mount(Course $course): void
    {
        $this->course = $course;
        // Optional: Zugriff prüfen (Teilnehmer des Kurses etc.)
        // abort_unless(auth()->user()?->isEnrolledIn($course), 403);
    }

    /** Aktueller Roter-Faden (Single) */
    public function getRoterFadenProperty(): ?File
    {
        return $this->course->files()
            ->where('type', 'roter_faden')
            ->latest('id')
            ->first();
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show-media', [
            'roterFaden' => $this->roterFaden
        ]);
    }
}
