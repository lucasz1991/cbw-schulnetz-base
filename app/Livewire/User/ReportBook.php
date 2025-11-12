<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\ReportBook as ReportBookModel;
use App\Models\ReportBookEntry;
use App\Models\Course;
use App\Models\CourseDay;

class ReportBook extends Component
{
    /** Optional: Maßnahme-Kontext */
    public ?string $massnahmeId = null;

    /** Kurs-/Tag-Auswahl */
    public array $courses = [];              // [{id,title}, ...]
    public ?int $selectedCourseId = null;

    public array $courseDays = [];           // [{id,date,label}, ...]
    public ?int $selectedCourseDayId = null; // aktueller Kurs-Tag


    public string $text = '';

    /** 0 = Entwurf, 1 = Fertig */
    public int $status = 0;

    /** Seitenleiste „Letzte Einträge“ */
    public array $recent = [];

    /** UI-Flags */
    public bool $isDirty = false;
    public bool $hasDraft = false;

    /** intern */
    protected ?int $reportBookId = null;      // wird lazy ermittelt/angelegt
    protected ?string $initialHash = null;

    public function mount(): void
    {
        // Kurse des Users laden
        $this->courses = $this->fetchUserCourses();

        // Auswahl initialisieren
        $this->selectedCourseId = $this->courses[0]['id'] ?? null;
        $this->loadCourseDays();

        // Ersten Day setzen (heute oder erster Tag)
        $this->selectedCourseDayId = $this->guessInitialCourseDayId();

        // Einträge laden (ReportBook kann noch nicht existieren → recent evtl. leer)
        $this->loadCurrentEntry();
        $this->loadRecent();
    }

    /* ======================= Datenbeschaffung ======================= */

protected function fetchUserCourses(): array
{
    $person = Auth::user()?->person;

    if ($person && method_exists($person, 'courses')) {
        $q = $person->courses()->withCount('dates');
        return $q->get(['courses.id','courses.title','courses.klassen_id','courses.planned_start_date','courses.planned_end_date'])
            ->map(fn($c) => [
                'id'                => $c->id,
                'klassen_id'        => $c->klassen_id,
                'title'             => $c->title ?? ('Kurs #'.$c->id),
                'planned_start_date'=> $c->planned_start_date,
                'planned_end_date'  => $c->planned_end_date,
            ])
            ->values()
            ->all();
    }

    return [];
}



    protected function loadCourseDays(): void
    {
        $this->courseDays = [];
        if (!$this->selectedCourseId) return;

        $days = CourseDay::query()
            ->where('course_id', $this->selectedCourseId)
            ->orderBy('date')->orderBy('start_time')
            ->get(['id','date','start_time','end_time']);

        $this->courseDays = $days->map(fn($d) => [
            'id'   => $d->id,
            'date' => Carbon::parse($d->date)->toDateString(),
            'label'=> Carbon::parse($d->date)->format('d.m.Y'),
        ])->values()->all();
    }

    protected function guessInitialCourseDayId(): ?int
    {
        if (!$this->courseDays) return null;
        $today = now()->toDateString();
        $hit = collect($this->courseDays)->firstWhere('date', $today);
        return $hit['id'] ?? ($this->courseDays[0]['id'] ?? null);
    }

    /* ======================= UI Events ======================= */

    public function selectCourse(int $courseId): void
    {
        if ($this->selectedCourseId === $courseId) return;

        $this->selectedCourseId = $courseId;
        $this->reportBookId = null; // neues Heft-Kontext
        $this->loadCourseDays();
        $this->selectedCourseDayId = $this->guessInitialCourseDayId();

        $this->loadCurrentEntry();
        $this->loadRecent();
    }

    public function selectCourseDay(int $courseDayId): void
    {
        if ($this->selectedCourseDayId === $courseDayId) return;

        $this->selectedCourseDayId = $courseDayId;
        $this->loadCurrentEntry();
    }

    public function updated($name, $value): void
    {
        if (in_array($name, ['title','text'])) {
            $this->recomputeFlags();
        }
    }

    /* ======================= Persistenzaktionen ======================= */

    public function save(): void
    {
        $this->ensureReportBookId(); // legt ReportBook on-demand an

        if (!$this->selectedCourseDayId) {
            $this->dispatch('toast', type: 'warning', message: 'Kein Kurstag ausgewählt.');
            return;
        }

        $day = CourseDay::find($this->selectedCourseDayId);
        if (!$day) {
            $this->dispatch('toast', type: 'warning', message: 'Kurstag nicht gefunden.');
            return;
        }

        $entry = ReportBookEntry::firstOrNew([
            'report_book_id' => $this->reportBookId,
            'course_day_id'  => $day->id,
        ]);

        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'status'       => 0,      // Entwurf
            'submitted_at' => null,
        ])->save();

        $this->status   = 0;
        $this->hasDraft = true;

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();

        $this->dispatch('toast', type: 'success', message: 'Entwurf gespeichert.');
        $this->loadRecent();
    }

    public function submit(): void
    {
        $this->ensureReportBookId();

        if (!$this->selectedCourseDayId) {
            $this->dispatch('toast', type: 'warning', message: 'Kein Kurstag ausgewählt.');
            return;
        }

        $day = CourseDay::find($this->selectedCourseDayId);
        if (!$day) {
            $this->dispatch('toast', type: 'warning', message: 'Kurstag nicht gefunden.');
            return;
        }

        $entry = ReportBookEntry::firstOrNew([
            'report_book_id' => $this->reportBookId,
            'course_day_id'  => $day->id,
        ]);

        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'status'       => 1,      // Fertig
            'submitted_at' => now(),
        ])->save();

        $this->status   = 1;
        $this->hasDraft = false;

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();

        $this->dispatch('toast', type: 'success', message: 'Eintrag fertiggestellt.');
        $this->loadRecent();
    }

    /* ======================= Loader / Helper ======================= */

    protected function ensureReportBookId(): void
    {
        if ($this->reportBookId) return;
        if (!$this->selectedCourseId) return;

        $book = ReportBookModel::firstOrCreate(
            [
                'user_id'      => Auth::id(),
                'course_id'    => $this->selectedCourseId,
                'massnahme_id' => $this->massnahmeId,
            ],
            [
                'title' => 'Mein Berichtsheft',
            ]
        );

        $this->reportBookId = $book->id;
    }

    protected function loadCurrentEntry(): void
    {
        // Resets
        $this->title = null;
        $this->text  = '';
        $this->status = 0;
        $this->hasDraft = false;

        if (!$this->selectedCourseId || !$this->selectedCourseDayId) {
            $this->initialHash = $this->curHash();
            $this->recomputeFlags();
            return;
        }

        // vorhandenes ReportBook ermitteln (nicht erzeugen!)
        $book = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->when($this->massnahmeId, fn($q) => $q->where('massnahme_id', $this->massnahmeId))
            ->first();

        $this->reportBookId = $book?->id;

        if (!$book) {
            $this->initialHash = $this->curHash();
            $this->recomputeFlags();
            return;
        }

        $entry = ReportBookEntry::where('report_book_id', $book->id)
            ->where('course_day_id', $this->selectedCourseDayId)
            ->first();

        if ($entry) {
            $this->text   = $entry->text ?? '';
            $this->status = (int) $entry->status;
            $this->hasDraft = $this->status === 0;
        }

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();
    }

    protected function loadRecent(): void
    {
        $this->recent = [];

        if (!$this->reportBookId) return;

        $this->recent = ReportBookEntry::query()
            ->where('report_book_id', $this->reportBookId)
            ->orderByDesc('entry_date')
            ->limit(7)
            ->get(['entry_date','text','status'])
            ->map(fn($r) => [
                'date'    => Carbon::parse($r->entry_date)->toDateString(),
                'status'  => (int) $r->status,
                'excerpt' => Str::of(strip_tags($r->text ?? ''))->limit(80)->value(),
            ])
            ->toArray();
    }

    protected function curHash(): string
    {
        return md5(($this->title ?? '') . '|' . ($this->text ?? ''));
    }

    protected function recomputeFlags(): void
    {
        $this->isDirty = $this->curHash() !== ($this->initialHash ?? '');
    }

    public function render()
    {
        return view('livewire.user.report-book');
    }
}
