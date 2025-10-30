<?php

namespace App\Livewire\User\Program\Course;

use App\Models\Course;
use App\Models\File;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseShowMedia extends Component
{
    public Course $course;
    public bool $openPreview = false;

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return $file->download();
    }

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
