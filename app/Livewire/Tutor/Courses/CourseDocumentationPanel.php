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

    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    public string $dayNotes = '';

        public bool $isDirty = false;

    public string $month;
    public int $perPage = 15;

    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible = false;

    public function mount(int $courseId): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::findOrFail($courseId);
        $this->month    = now()->format('Y-m');

        $today = now()->toDateString();
        $this->selectedDay = CourseDay::where('course_id', $courseId)
            ->whereDate('date', $today)
            ->first()
            ?: CourseDay::where('course_id', $courseId)->orderBy('date')->first();

    $this->selectedDayId = $this->selectedDay?->id;
    $this->dayNotes      = (string) ($this->selectedDay?->notes ?? '');
    $this->isDirty       = false;
    }

    #[On('calendarEventClick')]
    public function handleCalendarEventClick(...$args): void
    {
        $first = $args[0] ?? null;
        $id = is_array($first) ? (int) data_get($first, 'id') : (int) $first;
        if ($id > 0) {
            $this->selectDay($id);
        }
    }

    public function updatedDayNotes(): void
    {
        $this->isDirty       = true;
    }

public function selectDay(int $courseDayId): void
{
    $day = CourseDay::where('course_id', $this->courseId)->findOrFail($courseDayId);
    $this->selectedDay   = $day;
    $this->selectedDayId = $day->id;
    $this->dayNotes      = (string) ($day->notes ?? '');
    $this->isDirty       = false;

    $this->dispatch('daySelected', $day->id);
}

    public function selectPreviousDay(): void
    {
        if (!$this->selectedDay) return;

        $prev = $this->course->dates()
            ->where('date', '<', $this->selectedDay->date)
            ->orderByDesc('date')
            ->first();

        if ($prev) $this->selectDay($prev->id);
    }

    public function selectNextDay(): void
    {
        if (!$this->selectedDay) return;

        $next = $this->course->dates()
            ->where('date', '>', $this->selectedDay->date)
            ->orderBy('date')
            ->first();

        if ($next) $this->selectDay($next->id);
    }

    /**
     * Notizen speichern:
     * - 0 -> 1, wenn Text eingetragen wird
     * - 2 -> 1 + Signatur löschen, wenn Text nachträglich geändert wird
     */
public function saveNotes(): void
{
    if (!$this->selectedDayId) {
        return;
    }

    $day = CourseDay::where('course_id', $this->courseId)
        ->findOrFail($this->selectedDayId);

    $oldNotes = (string) ($day->notes ?? '');
    $newNotes = (string) $this->dayNotes;

    $notesChanged = trim($oldNotes) !== trim($newNotes);

    $day->notes = $newNotes;

    // … deine note_status- / Signatur-Logik wie besprochen …
    if ($notesChanged) {
        if (trim($newNotes) === '') {
            // Notizen geleert -> Status auf 0
            $day->note_status = CourseDay::NOTE_STATUS_MISSING;
        } else {
            // Notizen geändert -> Status auf 1
            $day->note_status = CourseDay::NOTE_STATUS_DRAFT;

            // Signatur löschen, wenn vorhanden
            $signatures = $day->files()
                ->where('type', 'sign_courseday_doku_tutor')
                ->get();
            foreach ($signatures as $signature) {
                $signature->delete();
            }
        }
    }

    $day->save();

    $this->selectedDay = $day;
    $this->isDirty     = false; // <— wichtig

    $this->dispatch('toast', type: 'success', message: 'Notizen gespeichert.');
}

    /**
     * Optional: Autosave + Status-Logik über saveNotes()
     */


    /**
     * „Fertigstellen“-Action:
     * -> Signatur-Flow starten
     */
    public function finalizeDay(): void
    {
        if (!$this->selectedDay) {
            return;
        }

        if (trim($this->dayNotes) === '') {
            $this->dispatch('toast', type: 'error', message: 'Bitte erst Notizen eintragen.');
            return;
        }

        // Signaturformular für CourseDay öffnen (SignatureForm bleibt unverändert)
            $this->dispatch('openSignatureForm', [
                'fileableType' => CourseDay::class,
                'fileableId'   => $this->selectedDayId,
                'fileType'     => 'sign_courseday_doku_tutor',
                'label'        => 'Unterrichtstag Dokumentation bestätigen',
                'confirmText'  => 'Ich bestätige, dass meine Angaben zu der <br><strong>Unterrichtstag Dokumentation <br>('. ($this->selectedDay?->date?->format('d.m.Y') ?? '') . ')</strong><br> vollständig und korrekt sind.',
            ]);
    }

    /**
     * Nach erfolgreichem Signieren:
     * -> Status auf 2 setzen
     */
    #[On('signatureCompleted')]
    public function handleSignatureCompleted(): void
    {
        // Wenn kein Tag ausgewählt ist, nichts tun
        if (!$this->selectedDay) {
            return;
        }

        // Status auf „fertig & unterschrieben“ setzen
        $this->selectedDay->note_status = CourseDay::NOTE_STATUS_COMPLETED;
        $this->selectedDay->save();

        // Model im State neu laden (falls Casts/Relations wichtig sind)
        $this->selectedDay = $this->selectedDay->fresh();

        // Editor ist nach der Unterschrift nicht mehr dirty
        $this->isDirty = false;

        // UI-Feedback
        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Dokumentation unterschrieben und abgeschlossen.'
        );

        // optional: kompletten Component-Refresh erzwingen
        $this->dispatch('$refresh');
    }

    protected function range(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start->toDateString(), $end->toDateString()];
    }

    public function getAllDaysProperty()
    {
        [$from, $to] = $this->range();
        return CourseDay::where('course_id', $this->courseId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')->orderBy('start_time')
            ->get(['id','course_id','date','start_time','end_time','note_status']);
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
        $this->selectPreviousDayPossible = $this->selectedDay
            ? $this->course->dates()->where('date', '<', $this->selectedDay->date)->exists()
            : false;

        $this->selectNextDayPossible = $this->selectedDay
            ? $this->course->dates()->where('date', '>', $this->selectedDay->date)->exists()
            : false;

        return view('livewire.tutor.courses.course-documentation-panel', [
            'course'                    => $this->course,
            'allDays'                   => $this->allDays,
            'selectedDay'               => $this->selectedDay,
            'selectedDayId'             => $this->selectedDayId,
            'selectPreviousDayPossible' => $this->selectPreviousDayPossible,
            'selectNextDayPossible'     => $this->selectNextDayPossible,
        ]);
    }
}
