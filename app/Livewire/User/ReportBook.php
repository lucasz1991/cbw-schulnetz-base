<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\ReportBook as ReportBookModel;
use App\Models\ReportBookEntry;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\User;
use App\Models\Person;

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



protected function fetchUserCourses(): array
{
    $personId = Auth::user()?->person?->id;
    if (!$personId) return [];

    $q = DB::table('courses')
        ->join('course_participant_enrollments as cpe', function ($join) use ($personId) {
            $join->on('courses.id', '=', 'cpe.course_id')
                 ->where('cpe.person_id', '=', $personId)
                 ->whereNull('cpe.deleted_at')
                 ->where('cpe.is_active', '=', 1);
        })
        ->leftJoin('course_days as cd', 'cd.course_id', '=', 'courses.id')
        ->leftJoin('report_books as rb', function ($join) {
            $join->on('rb.course_id', '=', 'courses.id')
                 ->where('rb.user_id', '=', Auth::id());
            if (!is_null($this->massnahmeId)) {
                $join->where('rb.massnahme_id', '=', $this->massnahmeId);
            }
        })
        ->leftJoin('report_book_entries as rbe', function ($join) {
            $join->on('rbe.report_book_id', '=', 'rb.id')
                 ->on('rbe.course_day_id', '=', 'cd.id');
        })
        ->whereNull('courses.deleted_at')
        ->select([
            'courses.id',
            'courses.title',
            'courses.klassen_id',
            'courses.planned_start_date',
            'courses.planned_end_date',
            DB::raw('COUNT(DISTINCT cd.id) AS days_total'),
            DB::raw('SUM(CASE WHEN rbe.id IS NOT NULL THEN 1 ELSE 0 END) AS days_with_entry'),
            DB::raw('SUM(CASE WHEN rbe.status = 0 THEN 1 ELSE 0 END) AS days_draft'),
            DB::raw('SUM(CASE WHEN rbe.status = 1 THEN 1 ELSE 0 END) AS days_submitted'),
            DB::raw('SUM(CASE WHEN rbe.status >= 1 THEN 1 ELSE 0 END) AS days_finished')
        ])
        ->groupBy(
            'courses.id','courses.title','courses.klassen_id',
            'courses.planned_start_date','courses.planned_end_date'
        )
        ->orderBy('courses.planned_start_date','asc');

    $today = Carbon::today();

    return collect($q->get())->map(function ($c) use ($today) {
        $start = $c->planned_start_date ? Carbon::parse($c->planned_start_date) : null;
        $end   = $c->planned_end_date   ? Carbon::parse($c->planned_end_date)   : null;
        $phase = 'unbekannt'; $phaseColor = 'slate';
        if ($start && $end) {
            if ($end->lt($today))       { $phase='beendet'; $phaseColor='gray'; }
            elseif ($start->gt($today)) { $phase='geplant'; $phaseColor='amber'; }
            else                        { $phase='läuft';   $phaseColor='blue'; }
        }

        $total     = (int)$c->days_total;
        $withEntry = (int)$c->days_with_entry;
        $finished  = (int)$c->days_finished; // jetzt >=1
        $missing   = max(0, $total - $withEntry);
        $hasAllFinished = ($total > 0 && $finished === $total);

        if ($total === 0) {
            $ampel = ['label'=>'Keine Kurstage','color'=>'slate','info'=>null];
        } elseif ($hasAllFinished) {
            $ampel = ['label'=>'Alle Tage fertig','color'=>'green','info'=>"$finished/$total"];
        } elseif ($withEntry === $total) {
            $ampel = ['label'=>'Alle belegt (noch nicht fertig)','color'=>'amber','info'=>"$finished/$total"];
        } else {
            $ampel = ['label'=>"$missing Tag(e) ohne Eintrag",'color'=>'red','info'=>"$finished/$total"];
        }

        return [
            'id'         => $c->id,
            'klassen_id' => $c->klassen_id,
            'title'      => $c->title ?? ('Kurs #'.$c->id),
            'planned_start_date' => $c->planned_start_date,
            'planned_end_date'   => $c->planned_end_date,
            'days_total'     => $total,
            'days_with_entry'=> $withEntry,
            'days_draft'     => (int)$c->days_draft,
            'days_submitted' => (int)$c->days_submitted,
            'days_finished'  => $finished,
            'days_missing'   => $missing,
            'phase'       => $phase,
            'phase_color' => $phaseColor,
            'ampel'       => $ampel,
        ];
    })->all();
}





protected function loadCourseDays(): void
{
    $this->courseDays = [];
    if (!$this->selectedCourseId) return;

    // Left join auf RB & RBE für diesen User (und Maßnahme), um Status je Tag zu holen
    $days = DB::table('course_days as cd')
        ->leftJoin('report_books as rb', function ($join) {
            $join->on('rb.course_id', '=', 'cd.course_id')
                 ->where('rb.user_id', '=', Auth::id());
            if (!is_null($this->massnahmeId)) {
                $join->where('rb.massnahme_id', '=', $this->massnahmeId);
            }
        })
        ->leftJoin('report_book_entries as rbe', function ($join) {
            $join->on('rbe.report_book_id', '=', 'rb.id')
                 ->on('rbe.course_day_id', '=', 'cd.id');
        })
        ->where('cd.course_id', $this->selectedCourseId)
        ->orderBy('cd.date')->orderBy('cd.start_time')
        ->get(['cd.id','cd.date','cd.start_time','cd.end_time','rbe.status']);

    $this->courseDays = $days->map(function ($d) {
        $date = \Illuminate\Support\Carbon::parse($d->date)->toDateString();
        $label = \Illuminate\Support\Carbon::parse($d->date)->format('d.m.Y');

        $status = is_null($d->status) ? null : (int)$d->status; // null = kein Eintrag
        $dot = match (true) {
            $status === null       => ['color'=>'gray',  'title'=>'Kein Eintrag'],
            $status === 0          => ['color'=>'amber', 'title'=>'Entwurf'],
            $status >= 1           => ['color'=>'green', 'title'=>'Fertig'],
        };

        return [
            'id'    => $d->id,
            'date'  => $date,
            'label' => $label,
            'status'=> $status,
            'dot'   => $dot,   // {color,title}
        ];
    })->values()->all();
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

    protected function reloadForCurrentCourse(): void
    {
        // Auswahl sichern
        $selectedCourseId    = $this->selectedCourseId;
        $selectedCourseDayId = $this->selectedCourseDayId;

        // Kurs-Aggregate neu laden (Ampel, X/Y, Missing)
        $this->courses = $this->fetchUserCourses();
        $this->selectedCourseId = $selectedCourseId; // Auswahl beibehalten

        // Kurstage (Status-Dots) aktualisieren
        $this->loadCourseDays();
        $this->selectedCourseDayId = $selectedCourseDayId; // Auswahl beibehalten

        // Aktuellen Eintrag + Recent neu laden (ReportBook-ID kann gerade erst entstanden sein)
        $this->loadCurrentEntry();
        $this->loadRecent();
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
            $this->reloadForCurrentCourse();
        $this->dispatch('toast', type: 'success', message: 'Entwurf gespeichert.');
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
    $this->reloadForCurrentCourse();
        $this->dispatch('toast', type: 'success', message: 'Eintrag fertiggestellt.');
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

    public function selectPrevCourse(): void
    {
        if (empty($this->courses)) return;

        $ids = array_column($this->courses, 'id');
        $idx = array_search($this->selectedCourseId, $ids, true);

        $prevIdx = is_int($idx) ? ($idx - 1 + count($ids)) % count($ids) : 0;
        $this->selectCourse((int)$ids[$prevIdx]);
    }

    public function selectNextCourse(): void
    {
        if (empty($this->courses)) return;

        $ids = array_column($this->courses, 'id');
        $idx = array_search($this->selectedCourseId, $ids, true);

        $nextIdx = is_int($idx) ? ($idx + 1) % count($ids) : 0;
        $this->selectCourse((int)$ids[$nextIdx]);
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
