<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;

class CourseShow extends Component
{
    public $courseId;

    public function mount($courseId)
    {
        $this->courseId = $courseId;
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show', [
            'courseId' => $this->courseId,
        ])->layout("layouts.app");
    }
}
