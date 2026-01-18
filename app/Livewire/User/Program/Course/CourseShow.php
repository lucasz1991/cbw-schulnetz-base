<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseParticipantEnrollment;

class CourseShow extends Component
{
    public string $klassenId;

    /** Kurs + Days als Arrays fürs Blade */
    public array $courseArray = [];
    public Course $course;
    public array $days = [];

    /** Navigation innerhalb der eingeschriebenen Kurse der Person */
    public ?array $prev = null;
    public ?array $next = null;
    public int $index = -1;
    public int $total = 0;

    public function mount(string $klassenId): void
    {
        $this->klassenId = $klassenId;

        $user   = Auth::user();
        $person = $user?->person;

        if (! $person) {
            abort(404);
        }

        // 1) Kurs laden
        $course = Course::query()
            ->where('klassen_id', $klassenId)
            ->first();

        if (! $course) {
            abort(404, 'Kurs nicht gefunden. Synchronisation läuft...');
        }

        $this->course = $course;

        // 2) Einschreibung prüfen (existiert eine Enrollment-Zeile?)
        $enrolled = CourseParticipantEnrollment::query()
            ->where('course_id', $this->course->id)
            ->where('person_id', $person->id)
            ->exists();

        if (! $enrolled) {
            abort(404, 'Nicht eingeschrieben.');
        }

        // 3) Kurs → ViewModel
        $this->courseArray = $this->mapCourse($this->course);
        $this->days   = $this->course->days->map(fn ($d) => $this->mapDay($d))->all();

        // 4) Prev/Nächster Kurs innerhalb der eigenen Einschreibungen
        $enrolledCourses = Course::query()
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->where('cpe.person_id', $person->id)
            ->orderBy('courses.planned_start_date')
            ->get([
                'courses.id as course_id',              // eindeutiger Alias
                'courses.klassen_id',
                'courses.title',
                'courses.planned_start_date',
                'courses.planned_end_date',
            ])
            ->map(fn ($c) => [
                'klassen_id' => $c->klassen_id,
                'title'      => $c->title,
                'start'      => $c->planned_start_date,
                'end'        => $c->planned_end_date,
                // 'course_id' => $c->course_id, // optional, falls du’s brauchst
            ])
            ->values();

        $this->total = $enrolledCourses->count();
        $this->index = $enrolledCourses->search(fn ($c) => $c['klassen_id'] === $this->klassenId);

        $this->prev = ($this->index > 0) ? $enrolledCourses[$this->index - 1] : null;
        $this->next = ($this->index !== false && $this->index + 1 < $this->total) ? $enrolledCourses[$this->index + 1] : null;
    }

    private function mapCourse(Course $c): array
    {
        $start = $c->planned_start_date ? Carbon::parse($c->planned_start_date) : null;
        $end   = $c->planned_end_date   ? Carbon::parse($c->planned_end_date)   : null;

        return [
            'id'          => $c->id,
            'klassen_id'  => $c->klassen_id,
            'title'       => $c->title,
            'description' => $c->description,
            'room'        => $c->room,
            'start'       => $start?->toDateString(),
            'end'         => $end?->toDateString(),
            'zeitraum_fmt'=> ($start && $end)
                                ? $start->locale('de')->isoFormat('ll') . ' – ' . $end->locale('de')->isoFormat('ll')
                                : '—',
            'status'      => $this->deriveCourseStatus($start, $end),
            'tutor'       => optional($c->primaryTutorPerson)->only(['vorname', 'nachname']),
        ];
    }

    private function mapDay(CourseDay $d): array
    {
        return [
            'id'     => $d->id,
            'date'   => $d->date ? Carbon::parse($d->date)->locale('de')->isoFormat('DD.MM.YYYY') : '—',
            'units'  => $d->units ?? null,
            'topic'  => $d->topic ?? null,
            'notes'  => $d->notes ?? null,
        ];
    }

    private function deriveCourseStatus(?Carbon $start, ?Carbon $end): string
    {
        $now = Carbon::now('Europe/Berlin');
        if ($start && $now->lt($start)) return 'geplant';
        if ($start && $end && $now->between($start, $end)) return 'aktiv';
        if ($end && $now->gt($end)) return 'abgeschlossen';
        return 'offen';
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show', [
            'course' => $this->course,
            'courseArray' => $this->courseArray,
            'days'   => $this->days,
            'prev'   => $this->prev,
            'next'   => $this->next,
            'index'  => $this->index,
            'total'  => $this->total,
        ])->layout('layouts.app');
    }
}
