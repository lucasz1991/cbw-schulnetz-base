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

    public function placeholder()
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

    public function render()
    {
        return view('livewire.user.program.course.course-show-media', [
            'roterFaden' => $this->roterFaden
        ]);
    }
}
