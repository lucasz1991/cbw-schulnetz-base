<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class CourseDocumentationPanel extends Component
{
    public int $courseId;
    public Course $course;

    // Auswahl
    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    // Editor
    public string $dayNotes = '';

    // UI
    public string $month; // YYYY-MM
    public int $perPage = 15; // nur falls Liste genutzt wird

    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible = false;

    public function mount(int $courseId): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::findOrFail($courseId);
        $this->month    = now()->format('Y-m');

        // Heutigen Tag oder ersten Tag wählen
        $today = now()->toDateString();
        $this->selectedDay = CourseDay::where('course_id', $courseId)
            ->whereDate('date', $today)
            ->first()
            ?: CourseDay::where('course_id', $courseId)->orderBy('date')->first();

        $this->selectedDayId = $this->selectedDay?->id;
        $this->dayNotes      = (string) ($this->selectedDay?->notes ?? '');
    }

    /** Auswahl per Kalenderklick (nimmt ID oder { id: ... } entgegen) */
    #[On('calendarEventClick')]
    public function handleCalendarEventClick(...$args): void
    {
        $first = $args[0] ?? null;
        $id = is_array($first) ? (int) data_get($first, 'id') : (int) $first;
        if ($id > 0) {
            $this->selectDay($id);
        }
    }

    public function selectDay(int $courseDayId): void
    {
        $day = CourseDay::where('course_id', $this->courseId)->findOrFail($courseDayId);
        $this->selectedDay   = $day;
        $this->selectedDayId = $day->id;
        $this->dayNotes      = (string) ($day->notes ?? '');

        $this->dispatch('daySelected', $day->id);
    }



    public function saveNotes(): void
    {
        if (!$this->selectedDayId) return;

        $day = CourseDay::where('course_id', $this->courseId)->findOrFail($this->selectedDayId);
        $day->notes = $this->dayNotes;
        $day->save();

        $this->dispatch('toast', type: 'success', message: 'Notizen gespeichert.');
    }

    public function selectPreviousDay(): void
    {
        if (!$this->selectedDay) return;

        $prev = $this->course->dates()
            ->where('date', '<', $this->selectedDay->date)
            ->orderByDesc('date')->first();

        if ($prev) $this->selectDay($prev->id);
    }

    public function selectNextDay(): void
    {
        if (!$this->selectedDay) return;

        $next = $this->course->dates()
            ->where('date', '>', $this->selectedDay->date)
            ->orderBy('date')->first();

        if ($next) $this->selectDay($next->id);
    }

    protected function range(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start->toDateString(), $end->toDateString()];
    }

    public function updatedDayNotes(): void
    {
        // optional: nichts tun, wenn du nur per Button speichern willst
        // oder: simple Throttle
        static $last = 0;
        if (microtime(true) - $last < 1.0) return; // 1s throttle
        $last = microtime(true);
        $this->saveNotes();
    }
 
    public function getAllDaysProperty()
    {
        [$from, $to] = $this->range();
        return CourseDay::where('course_id', $this->courseId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')->orderBy('start_time')
            ->get(['id','course_id','date','start_time','end_time']);
    }

    public function render()
    {
        $this->selectPreviousDayPossible = $this->selectedDay
            ? $this->course->dates()->where('date', '<', $this->selectedDay->date)->exists()
            : false;

        $this->selectNextDayPossible = $this->selectedDay
            ? $this->course->dates()->where('date', '>', $this->selectedDay->date)->exists()
            : false;

        return view('livewire.tutor.courses.course-documentation-panel', [
            'course'     => $this->course,
            'allDays'    => $this->allDays,
            'selectedDay'=> $this->selectedDay,
            'selectedDayId' => $this->selectedDayId,
            'selectPreviousDayPossible' => $this->selectPreviousDayPossible,
            'selectNextDayPossible'     => $this->selectNextDayPossible,
        ]);
    }
}
