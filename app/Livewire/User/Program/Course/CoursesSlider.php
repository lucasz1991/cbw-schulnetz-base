<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\CourseMaterialAcknowledgement;
use App\Models\CourseRating;
use App\Models\Person;
use App\Support\CurrentParticipantCourseScope;
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

        $this->person = $this->resolveCurrentPerson();
        if (! $this->person) {
            abort(404);
        }

        // Aktuellen Kurs über klassen_id holen (für courseId + Index)
        $currentCourse = Course::query()
            ->select('courses.*')
            ->join('course_participant_enrollments as cpe', function ($join) {
                $join->on('cpe.course_id', '=', 'courses.id')
                    ->whereNull('cpe.deleted_at')
                    ->where('cpe.is_active', true);
            })
            ->where('courses.klassen_id', $klassenId)
            ->where(function ($query) {
                CurrentParticipantCourseScope::applyForPerson($query, $this->person, 'cpe', 'courses');
            })
            ->firstOrFail();

        $this->courseId = (int) $currentCourse->id;

        // Alle eingeschriebenen Kurse laden (für Slider)
        $this->enrolledCourses = Course::query()
            ->select('courses.*') // wichtig bei join
            ->with(['days'])
            ->withCount('days')
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->whereNull('cpe.deleted_at')
            ->where('cpe.is_active', true)
            ->where(function ($query) {
                CurrentParticipantCourseScope::applyForPerson($query, $this->person, 'cpe', 'courses');
            })
            ->orderBy('courses.planned_start_date')
            ->get();

        $courseIds = $this->enrolledCourses
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $ratedCourseIds = [];
        $materialAckCourseIds = [];

        if (! empty($courseIds)) {
            $ratedCourseIds = CourseRating::query()
                ->where('user_id', Auth::id())
                ->whereIn('course_id', $courseIds)
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $materialAckCourseIds = CourseMaterialAcknowledgement::query()
                ->where('person_id', $this->person->id)
                ->whereIn('course_id', $courseIds)
                ->whereNotNull('acknowledged_at')
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $ratedLookup = array_flip($ratedCourseIds);
        $materialAckLookup = array_flip($materialAckCourseIds);

        $this->enrolledCourses->each(function (Course $course) use ($ratedLookup, $materialAckLookup) {
            $course->setAttribute('has_rating', isset($ratedLookup[(int) $course->id]));
            $course->setAttribute('has_material_ack', isset($materialAckLookup[(int) $course->id]));
        });

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

    protected function resolveCurrentPerson(): ?Person
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'resolvePortalDrivingPerson')) {
            return $user->resolvePortalDrivingPerson() ?? $user->person;
        }

        return $user?->person;
    }
}
