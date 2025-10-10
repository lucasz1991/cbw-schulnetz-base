<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\CourseParticipantEnrollment;

class CourseShowOverview extends Component
{
    /** Wird vom Parent (CourseShow) übergeben */
    public string $klassenId;

    public array $course = [];
    public array $stats = [];
    public ?array $prev = null;
    public ?array $next = null;
    public int $index = -1;
    public int $total = 0;

    public function mount(string $klassenId): void
    {
        $this->klassenId = $klassenId;

        $person = Auth::user()?->person;
        if (! $person) abort(404);

        $course = Course::query()
            ->where('klassen_id', $klassenId)
            ->firstOrFail();

        // Einschreibung erzwingen
        $enrolled = CourseParticipantEnrollment::query()
            ->where('course_id', $course->id)
            ->where('person_id', $person->id)
            ->exists();
        if (! $enrolled) abort(404, 'Nicht eingeschrieben.');

        // ViewModel
        $start = $course->planned_start_date ? Carbon::parse($course->planned_start_date) : null;
        $end   = $course->planned_end_date   ? Carbon::parse($course->planned_end_date)   : null;

        $this->course = [
            'id'          => $course->id,
            'klassen_id'  => $course->klassen_id,
            'title'       => $course->title,
            'description' => $course->description,
            'room'        => $course->room,
            'start'       => $start?->toDateString(),
            'end'         => $end?->toDateString(),
            'zeitraum_fmt'=> ($start && $end) ? $start->locale('de')->isoFormat('ll').' – '.$end->locale('de')->isoFormat('ll') : '—',
            'status'      => $this->status($start, $end),
            'tutor'       => $course->primaryTutorPerson ? trim($course->primaryTutorPerson->vorname.' '.$course->primaryTutorPerson->nachname) : null,
        ];

        $this->stats = [
            'tage'      => $course->days->count(),
            'einheiten' => (int) $course->days->sum('units'),
            'start'     => $this->course['start'] ?? null,
            'end'       => $this->course['end'] ?? null,
        ];



        // Navigation innerhalb der eigenen Kurse (Prev/Nächster) – Links gehen auf den PARENT (CourseShow)
        $enrolledCourses = Course::query()
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->where('cpe.person_id', $person->id)
            ->orderBy('courses.planned_start_date')
            ->get([
                'courses.klassen_id',
                'courses.title',
                'courses.planned_start_date',
            ]);

        $this->total = $enrolledCourses->count();
        $this->index = $enrolledCourses->search(fn($c) => $c->klassen_id === $this->klassenId);
        $this->prev  = ($this->index > 0) ? [
            'klassen_id' => $enrolledCourses[$this->index - 1]->klassen_id,
            'title'      => $enrolledCourses[$this->index - 1]->title,
        ] : null;
        $this->next  = ($this->index !== false && $this->index + 1 < $this->total) ? [
            'klassen_id' => $enrolledCourses[$this->index + 1]->klassen_id,
            'title'      => $enrolledCourses[$this->index + 1]->title,
        ] : null;
    }

    private function status(?Carbon $start, ?Carbon $end): string
    {
        $now = Carbon::now('Europe/Berlin');
        if ($start && $now->lt($start)) return 'Geplant';
        if ($start && $end && $now->between($start, $end)) return 'Laufend';
        if ($end && $now->gt($end)) return 'Abgeschlossen';
        return 'Offen';
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class=" animate-pulse">
                <div class="">
                    <header class="container mx-auto px-5 py-6 flex items-start justify-between">
                        <div>
                        <h1 class="text-2xl font-semibold"><div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div></h1>
                        <p class="text-gray-600"><div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div></p>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-gray-800"></span>
                        </div>
                        <div class="flex items-center gap-2">
                        <x-buttons.button-basic :size="'sm'">
                            Bewerten
                        </x-buttons.button-basic>
                            <x-buttons.button-basic :size="'sm'"
                            wire:navigate>← Vorheriger</x-buttons.button-basic>
                            <x-buttons.button-basic :size="'sm'"
                            wire:navigate>Nächster →</x-buttons.button-basic>
                        </div>
                    </header>
                    <section class="container mx-auto px-5 pb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white rounded-lg border shadow p-4">
                            <p class="text-xs text-gray-500 mb-4">Tage</p>
                            <p class="text-2xl font-semibold"><div class="h-2.5 bg-gray-300 rounded-full w-48 my-4"></div></p>
                        </div>
                        <div class="bg-white rounded-lg border shadow p-4">
                            <p class="text-xs text-gray-500 mb-4">Einheiten (gesamt)</p>
                            <p class="text-2xl font-semibold"><div class="h-2.5 bg-gray-300 rounded-full w-48 my-4"></div></p>
                        </div>
                        <div class="bg-white rounded-lg border shadow p-4">
                            <p class="text-xs text-gray-500 mb-4">Beginn</p>
                            <p class="text-2xl font-semibold"><div class="h-2.5 bg-gray-300 rounded-full w-48 my-4"></div></p>
                        </div>
                        <div class="bg-white rounded-lg border shadow p-4">
                            <p class="text-xs text-gray-500 mb-4">Ende</p>
                            <p class="text-2xl font-semibold"><div class="h-2.5 bg-gray-300 rounded-full w-48 my-4"></div></p>
                        </div>
                        </div>
                    </section>
                </div>
            </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show-overview');
    }
}
