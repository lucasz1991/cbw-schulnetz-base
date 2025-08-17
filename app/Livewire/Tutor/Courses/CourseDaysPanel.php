<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use Livewire\Attributes\On;

class CourseDaysPanel extends Component
{
    use WithPagination, WithoutUrlPagination;

    public int $courseId;
    public Course $course;
    public string $courseTitle = 'Kurstermin';

    // UI / Filter
    public string $month;        // YYYY-MM (Filter)
    public int $perPage = 15;

    // Auswahl
    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    public ?int $selectedDaySessionId = null;
    public ?string $selectedDaySessionTopic = null; // z.B. "8:00"
    public ?string $selectedDaySessionNotes = '';
    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible = false;

    public function mount(int $courseId): void
    {
        $this->courseId = $courseId;
        $this->course = Course::findOrFail($courseId);
        $this->courseTitle = (string) (Course::whereKey($courseId)->value('title') ?? 'Kurstermin');
        $this->month = now()->format('Y-m');
        // 1) Heutigen Tag suchen
        $today = now()->toDateString();

        $this->selectedDay = CourseDay::query()
            ->where('course_id', $courseId)
            ->whereDate('date', $today)
            ->first();

        // 2) Fallback: erster Kurstag (frühestes Datum), wenn heute nicht existiert
        if (!$this->selectedDay) {
            $this->selectedDay = CourseDay::query()
                ->where('course_id', $courseId)
                ->orderBy('date', 'asc')
                ->first();
        }

        // 3) IDs/Monat sicher setzen (auch wenn gar kein Tag existiert)
        $this->selectedDayId = $this->selectedDay?->id;
        $this->selectedDaySessionId = $this->selectedDay?->getSessions()->first()?->id;
        $this->selectedDaySessionNotes = (string) ($this->selectedDay?->getSessionNotes($this->selectedDaySessionId ?? '') ?? '');
        $this->selectedDaySessionTopic = (string) ($this->selectedDay?->getSessionTopic($this->selectedDaySessionId ?? '') ?? '');
    }

    /** Nimmt Skalar ODER { id: ... } entgegen (vom Kalender) */
    #[On('calendarEventClick')]
    public function handleCalendarEventClick(...$args): void
    {
        $first = $args[0] ?? null;
        $id = is_array($first) ? (int) data_get($first, 'id') : (int) $first;
        if ($id > 0) {
            $this->selectDay($id);
        }
    }


    /** Wenn Session gewechselt wird: Notes nachladen */
    public function updatedSelectedDaySessionId(): void
    {
        $this->selectedDaySessionTopic = (string) ($this->selectedDay?->getSessionTopic($this->selectedDaySessionId ?? '') ?? '');
        $this->selectedDaySessionNotes = (string) ($this->selectedDay?->getSessionNotes($this->selectedDaySessionId ?? '') ?? '');
    }

    /** Wenn Topic geändert wird -> ins JSON schreiben + Model speichern */
    public function updatedSelectedDaySessionTopic(): void
    {
        if ($this->selectedDay && $this->selectedDaySessionId) {
            $this->selectedDay->setSessionTopic($this->selectedDaySessionId, $this->selectedDaySessionTopic);
        }
    }

    /** Notes gespeichert -> ins JSON schreiben + Model speichern */
    public function updatedSelectedDaySessionNotes(): void
    {
        if ($this->selectedDay && $this->selectedDaySessionId) {
            $this->selectedDay->setSessionNotes($this->selectedDaySessionId, $this->selectedDaySessionNotes);
        }
    }

    public function selectDay(int $courseDayId): void
    {
        $day = CourseDay::query()
            ->where('course_id', $this->courseId)
            ->findOrFail($courseDayId);

        $this->selectedDayId = $day->id;
        $this->selectedDay   = $day;

        $this->selectedDaySessionId = $this->selectedDay?->getSessions()->first()?->id;
        $this->selectedDaySessionTopic = (string) ($this->selectedDay?->getSessionTopic($this->selectedDaySessionId ?? '') ?? '');
        $this->selectedDaySessionNotes = (string) ($this->selectedDay?->getSessionNotes($this->selectedDaySessionId ?? '') ?? '');

        // Event für Parent/andere Komponenten
        $this->dispatch('daySelected', $day->id);
        // Optional auch als Browser-Event:
        // $this->dispatch('daySelected', ['selectedDayId' => $day->id]);
    }

    public function updatedMonth(): void
    {
        $this->resetPage();
        if ($this->selectedDay && !$this->isInCurrentRange($this->selectedDay->date)) {
            $this->selectedDay = null;
            $this->selectedDayId = null;
        }
    }

    protected function range(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start->toDateString(), $end->toDateString()];
    }

    protected function isInCurrentRange(string|\DateTimeInterface $date): bool
    {
        [$from, $to] = $this->range();
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
        return $d >= $from && $d <= $to;
    }

    /** Für den Kalender: alle Days im Monat (ungepaginert) */
    public function getAllDaysProperty()
    {
        [$from, $to] = $this->range();

        return CourseDay::query()
            ->where('course_id', $this->courseId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['id','course_id','date','start_time','end_time']);
    }

    /** Für die Liste: paginiert */
    public function getDaysProperty()
    {
        [$from, $to] = $this->range();

        return CourseDay::query()
            ->where('course_id', $this->courseId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($this->perPage);
    }



    public function selectPreviousDay()
    {
        if (!$this->selectPreviousDayPossible) return;

        $this->selectedDay = $this->course
            ->dates()
            ->where('date', '<', $this->selectedDay->date)
            ->orderByDesc('date')
            ->first();

        $this->selectedDayId = $this->selectedDay?->id;
    }

    public function selectNextDay()
    {
        if (!$this->selectNextDayPossible) return;

        $this->selectedDay = $this->course
            ->dates()
            ->where('date', '>', $this->selectedDay->date)
            ->orderBy('date')
            ->first();

        $this->selectedDayId = $this->selectedDay?->id;
    }



    public function render()
    {
        $this->selectPreviousDayPossible = $this->course->dates()->where('date', '<', $this->selectedDay->date)->exists();
        $this->selectNextDayPossible     = $this->course->dates()->where('date', '>', $this->selectedDay->date)->exists();

        return view('livewire.tutor.courses.course-days-panel', [
            'course' => $this->course,
            'days'    => $this->days,
            'allDays' => $this->allDays,
            'title'   => $this->courseTitle,
            'selectedDay' => $this->selectedDay,
            'selectedDaySessionId' => $this->selectedDaySessionId,
            'selectPreviousDayPossible' => $this->selectPreviousDayPossible,
            'selectNextDayPossible' => $this->selectNextDayPossible,
        ]);
    }
}
