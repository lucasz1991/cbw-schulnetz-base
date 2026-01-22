<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CoursesSlider extends Component
{
    public string $klassenId;

    public ?Person $person = null;

    public EloquentCollection $enrolledCourses;

    public ?int $courseId = null;
    public int $index = -1;
    public int $total = 0;

    public ?array $prev = null; // ['klassen_id' => ..., 'title' => ...]
    public ?array $next = null; // ['klassen_id' => ..., 'title' => ...]

    public function mount(string $klassenId): void
    {
        $this->klassenId = $klassenId;

        $this->person = Auth::user()?->person;
        if (! $this->person) {
            abort(404);
        }

        // Aktuellen Kurs über klassen_id holen (für courseId + Index)
        $currentCourse = Course::query()
            ->select('courses.*')
            ->where('klassen_id', $klassenId)
            ->firstOrFail();

        $this->courseId = (int) $currentCourse->id;

        // Alle eingeschriebenen Kurse laden (für Slider)
        $this->enrolledCourses = Course::query()
            ->select('courses.*') // wichtig bei join
            ->with(['days'])
            ->withCount('days')
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->where('cpe.person_id', $this->person->id)
            ->orderBy('courses.planned_start_date')
            ->get();

        $this->total = $this->enrolledCourses->count();

        $foundIndex = $this->enrolledCourses->search(
            fn (Course $c) => $c->klassen_id === $this->klassenId
        );

        $this->index = ($foundIndex === false) ? -1 : (int) $foundIndex;

        $this->prev = ($this->index > 0)
            ? [
                'klassen_id' => $this->enrolledCourses[$this->index - 1]->klassen_id,
                'title'      => $this->enrolledCourses[$this->index - 1]->title,
            ]
            : null;

        $this->next = ($this->index >= 0 && $this->index + 1 < $this->total)
            ? [
                'klassen_id' => $this->enrolledCourses[$this->index + 1]->klassen_id,
                'title'      => $this->enrolledCourses[$this->index + 1]->title,
            ]
            : null;
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-20 w-full relative animate-pulse">
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
        return view('livewire.user.program.course.courses-slider');
    }
}
