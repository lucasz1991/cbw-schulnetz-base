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
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBook extends Component
{
    /** Optional: Maßnahme-Kontext */
    public ?string $massnahmeId = null;

    /** Kurs-/Tag-Auswahl */
    public array $courses = [];              // [{id,title}, ...]
    public ?int $selectedCourseId = null;
    public ?string $selectedCourseName = null; 

    public array $courseDays = [];           // [{id,date,label}, ...]
    public ?int $selectedCourseDayId = null; // aktueller Kurs-Tag


    public string $text = '';

    /** 0 = Fehlend, 1 = Entwurf, 2 = Fertig */
    public int $status = -1;

    /** Seitenleiste „Letzte Einträge“ */
    public array $recent = [];

    /** UI-Flags */
    public bool $isDirty = false;
    public bool $hasDraft = false;

    /** intern */
    protected ?int $reportBookId = null;      // wird lazy ermittelt/angelegt
    public ?int $reportBookEntryId = null;    // wird lazy ermittelt/angelegt
    protected ?string $initialHash = null;

    public int $editorVersion = 0;

    // Signatur-Modal (direkt per Variablen gesteuert)
    public bool $signatureModalOpen = false;
    public ?int $signatureReportBookId = null;
    public ?int $signatureCourseId = null;
    public ?int $signatureEntryId = null;
    public ?string $signatureCourseName = null;

protected $listeners = [
    'signatureCompleted' => 'signatureCompleted',
    'signatureAborted' => 'signatureAborted',
];


    public function mount(): void
    {
        // Kurse des Users laden
        $this->courses = $this->fetchUserCourses();

        // Auswahl initialisieren
        $this->selectedCourseId = $this->courses[0]['id'] ?? null;
            $this->selectedCourseName = ($this->courses[0]['title'] ?? null) ?: '—';
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
        ->get([
            'cd.id',
            'cd.date',
            'cd.start_time',
            'cd.end_time',
            'cd.notes',          // ← Tutor-Doku
            'rbe.status'
        ]);

    $this->courseDays = $days->map(function ($d) {

        $date  = Carbon::parse($d->date)->toDateString();
        $label = Carbon::parse($d->date)->format('d.m.Y');

        $status = is_null($d->status) ? null : (int)$d->status;
        $dot = match (true) {
            $status === null       => ['color'=>'gray',  'title'=>'Kein Eintrag'],
            $status === 0          => ['color'=>'amber', 'title'=>'Entwurf'],
            $status >= 1           => ['color'=>'green', 'title'=>'Fertig'],
        };

        return [
            'id'          => $d->id,
            'date'        => $date,
            'label'       => $label,
            'status'      => $status,
            'dot'         => $dot,
            'hasTutorDoc' => !empty($d->notes),  // ← HIER!
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
        if ($c = $this->courseById($courseId)) {
            $this->selectedCourseName = $c['title'] ?? '—';
        } else {
            $this->selectedCourseName = Course::whereKey($courseId)->value('title') ?? '—';
        }
        $this->reportBookId = null;
        $this->reportBookEntryId = null;
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

    // ----------------------------------------------------------
    // 🆕 NEU: Wenn der Eintrag auf ENTWURF geht → Signatur löschen
    // ----------------------------------------------------------

    $book = ReportBookModel::with('files')->find($this->reportBookId);

    if ($book) {
        // falls Modell-Hilfsmethode existiert (empfohlen)
        if (method_exists($book, 'participantSignatureFile')) {
            $sig = $book->participantSignatureFile();
        } else {
            // Fallback auf dateityp
            $sig = $book->files()
                ->where('type', 'participant_signature')
                ->latest()
                ->first();
        }

        if ($sig) {
            $sig->delete(); // Model + Datei weg
        }
    }

    // ----------------------------------------------------------

    // Entwurf speichern
    $entry->fill([
        'entry_date'   => $day->date,
        'text'         => $this->text,
        'status'       => 0,      // ENTWURF
        'submitted_at' => null,
    ])->save();

    $this->reportBookEntryId = $entry->id;

    $this->status   = 0;
    $this->hasDraft = true;

    $this->initialHash = $this->curHash();
    $this->recomputeFlags();
    $this->reloadForCurrentCourse();
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
        /* ======================= bevor status änderung ======================= */
        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'submitted_at' => now(),
        ])->save();
         
        $this->reportBookEntryId = $entry->id;
        $needsSignature = $this->checkCourseCompletionAndOpenSignature();

        if (!$needsSignature) {
            $entry->fill([
                'status'       => 1,
            ])->save();
    
            $this->status   = 1;
            $this->hasDraft = false;
    
            $this->initialHash = $this->curHash();
            $this->recomputeFlags();
            $this->reloadForCurrentCourse();
            $this->dispatch('toast', type: 'success', message: 'Eintrag fertiggestellt.');
        }

    }

public function signatureCompleted(): void
{
    // Modal im Parent schließen
    $this->signatureModalOpen = false;

    $day = CourseDay::find($this->selectedCourseDayId);
    if (!$day) {
        $this->dispatch('toast', type: 'warning', message: 'Kurstag nicht gefunden.');
        return;
    }

    // Versuche den Eintrag direkt per ID zu holen (solltest du haben)
    if ($this->reportBookEntryId) {
        $entry = ReportBookEntry::find($this->reportBookEntryId);
    } else {
        // Fallback: per Kombination aus report_book_id + course_day_id
        $entry = ReportBookEntry::where('report_book_id', $this->reportBookId)
            ->where('course_day_id', $day->id)
            ->first();
    }

    if (!$entry) {
        // Hier sollte man lieber abbrechen als einen neuen Eintrag ohne entry_date anzulegen
        $this->dispatch('toast', type: 'warning', message: 'Kein Eintrag zum Unterschreiben gefunden.');
        return;
    }

    $entry->status = 1;
    // optional: falls submitted_at noch null ist, jetzt setzen
    if (!$entry->submitted_at) {
        $entry->submitted_at = now();
    }
    $entry->save();

    $this->status   = 1;
    $this->hasDraft = false;

    $this->initialHash = $this->curHash();
    $this->recomputeFlags();
    $this->reloadForCurrentCourse();
    $this->dispatch('toast', type: 'success', message: 'Berichtsheft für diesen Kurs wurde unterschrieben.');
}

public function signatureAborted(): void
{
    $this->signatureModalOpen = false;
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

protected function checkCourseCompletionAndOpenSignature(): bool
{
    if (!$this->reportBookId || !$this->selectedCourseDayId) {
        return false;
    }

    $book = ReportBookModel::with(['course.days'])->find($this->reportBookId);
    if (!$book || !$book->course) {
        return false;
    }

    // Alle Kurstage des Kurses
    $totalDays = $book->course->days->count();
    if ($totalDays === 0) {
        return false;
    }

    // Safety: aktueller Tag muss zu diesem Kurs gehören
    $isCurrentDayInCourse = $book->course->days->contains('id', $this->selectedCourseDayId);
    if (!$isCurrentDayInCourse) {
        return false;
    }

    // Fertige Einträge (status >= 1) – OHNE den gerade bearbeiteten Tag mitzuzählen
    $finishedDays = ReportBookEntry::query()
        ->where('report_book_id', $this->reportBookId)
        ->where('status', '>=', 1)
        ->distinct('course_day_id')
        ->count('course_day_id');

    // Aktueller Tag hat jetzt einen Eintrag (Entwurf / Submitted),
    // ist aber noch nicht "fertig" (status 1). Für "alles fertig"
    // gilt dann: finishedDays + 1 === totalDays.
    if ($finishedDays + 1 !== $totalDays) {
        return false;
    }

    // Prüfen: gibt es für dieses Berichtsheft schon eine Teilnehmer-Signatur?
    if ($book->participantSignatureFile()) {
        return false;
    }

    // Jetzt Modal öffnen – alle anderen Tage fertig, dieser hier der letzte.
    $this->signatureReportBookId = $book->id;
    $this->signatureCourseId     = $book->course->id;
    $this->signatureCourseName   = $book->course->title;
    $this->signatureEntryId      = $this->reportBookEntryId;
    $this->signatureModalOpen    = true;

    return true;
}


    protected function loadCurrentEntry(): void
    {
        // Resets
        $this->reportBookEntryId = null;
        $this->title = null;
        $this->text  = '';
        $this->status = -1;
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
            $this->reportBookEntryId = $entry->id;
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

    public function selectPrevDay(): void
    {
        if (empty($this->courseDays) || !$this->selectedCourseDayId) return;

        $ids = array_map(fn($d) => (int)$d['id'], $this->courseDays);
        $idx = array_search((int)$this->selectedCourseDayId, $ids, true);

        $prevIdx = is_int($idx) ? ($idx - 1 + count($ids)) % count($ids) : 0;
        $this->selectCourseDay((int)$ids[$prevIdx]);
    }

    public function selectNextDay(): void
    {
        if (empty($this->courseDays) || !$this->selectedCourseDayId) return;

        $ids = array_map(fn($d) => (int)$d['id'], $this->courseDays);
        $idx = array_search((int)$this->selectedCourseDayId, $ids, true);

        $nextIdx = is_int($idx) ? ($idx + 1) % count($ids) : 0;
        $this->selectCourseDay((int)$ids[$nextIdx]);
    }

    protected function courseById(int $id): ?array
    {
        foreach ($this->courses as $c) {
            if ((int)($c['id'] ?? 0) === $id) {
                return $c;
            }
        }
        return null;
    }


    protected function curHash(): string
    {
        return md5(($this->title ?? '') . '|' . ($this->text ?? ''));
    }

    protected function recomputeFlags(): void
    {
        $this->isDirty = $this->curHash() !== ($this->initialHash ?? '');
    }

    public function importTutorDocToDraft(): void
    {
        if (!$this->selectedCourseDayId) {
            $this->dispatch('toast', type: 'warning', message: 'Kein Kurstag ausgewählt.');
            return;
        }

        // Tutor-Doku direkt aus course_days.notes
        $day = CourseDay::find($this->selectedCourseDayId, ['id','date','notes']);
        if (!$day) {
            $this->dispatch('toast', type: 'warning', message: 'Kurstag nicht gefunden.');
            return;
        }

        $doc = trim((string)($day->notes ?? ''));
        if ($doc === '') {
            $this->dispatch('toast', type: 'info', message: 'Keine Dozenten-Dokumentation vorhanden.');
            return;
        }

        // an deinen Text anhängen
        if (trim($this->text) !== '') {
            $this->text = rtrim($this->text) . "\n\n" . $doc;
        } else {
            $this->text = $doc;
        }

        // als Entwurf speichern
        $this->ensureReportBookId();

        $entry = ReportBookEntry::firstOrNew([
            'report_book_id' => $this->reportBookId,
            'course_day_id'  => $day->id,
        ]);

        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'status'       => 0,     // ENTWURF
            'submitted_at' => null,
        ])->save();

        $this->status   = 0;
        $this->hasDraft = true;

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();
        $this->reloadForCurrentCourse();
        $this->editorVersion++;

        $this->dispatch('toast', type: 'success', message: 'Dozenten-Dokumentation übernommen und als Entwurf gespeichert.');
    }


public function exportReportEntry(): ?StreamedResponse
{
    if (!$this->selectedCourseId || !$this->selectedCourseDayId) {
        $this->dispatch('toast', type: 'warning', message: 'Kurs und Kurstag auswählen.');
        return null;
    }

    $book = ReportBookModel::where('user_id', Auth::id())
        ->where('course_id', $this->selectedCourseId)
        ->when($this->massnahmeId, fn($q) => $q->where('massnahme_id', $this->massnahmeId))
        ->first();

    if (!$book) {
        $this->dispatch('toast', type: 'warning', message: 'Kein Berichtsheft vorhanden.');
        return null;
    }

    $entry = ReportBookEntry::where('report_book_id', $book->id)
        ->where('course_day_id', $this->selectedCourseDayId)
        ->first();

    if (!$entry) {
        $this->dispatch('toast', type: 'warning', message: 'Kein Eintrag vorhanden.');
        return null;
    }

    $entry->entry_date = \Carbon\Carbon::parse($entry->entry_date);

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'   => 'single',
        'entry'  => $entry,
        'course' => $book->course,
        'user'   => Auth::user(),
        'title'  => 'Bericht '.$entry->entry_date->format('d.m.Y'),
    ]);

    return response()->streamDownload(
        fn() => print($pdf->output()),
        'bericht-'.$entry->entry_date->format('Y-m-d').'.pdf'
    );
}


public function exportReportModule(): ?StreamedResponse
{
    $book = ReportBookModel::with(['course','entries' => fn($q) => $q->orderBy('entry_date')])
        ->where('user_id', Auth::id())
        ->where('course_id', $this->selectedCourseId)
        ->when($this->massnahmeId, fn ($q) => $q->where('massnahme_id', $this->massnahmeId))
        ->first();

    if (!$book || $book->entries->isEmpty()) {
        $this->dispatch('toast', type: 'warning', message: 'Keine Einträge vorhanden.');
        return null;
    }

    foreach ($book->entries as $e) {
        $e->entry_date = \Carbon\Carbon::parse($e->entry_date);
    }

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'    => 'module',
        'entries' => $book->entries,
        'course'  => $book->course,
        'user'    => Auth::user(),
        'title'   => 'Berichtsheft – '.$book->course->klassen_id,
    ]);

    return response()->streamDownload(
        fn() => print($pdf->output()),
        'berichtsheft-'.$book->course->klassen_id.'.pdf'
    );
}

public function exportReportAll(): ?StreamedResponse
{
    $books = ReportBookModel::with([
            'course',
            'entries' => fn($q) => $q->orderBy('entry_date'),
        ])
        ->where('user_id', Auth::id())
        ->when($this->massnahmeId, fn($q) => $q->where('massnahme_id', $this->massnahmeId))
        ->whereHas('entries')
        ->get();

    if ($books->isEmpty()) {
        $this->dispatch('toast', type: 'warning', message: 'Keine Einträge vorhanden.');
        return null;
    }

    foreach ($books as $book) {
        foreach ($book->entries as $e) {
            $e->entry_date = \Carbon\Carbon::parse($e->entry_date);
        }
    }

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'  => 'all',
        'books' => $books,
        'user'  => Auth::user(),
        'title' => 'Berichtsheft – Alle Kurse',
    ]);

    return response()->streamDownload(
        fn() => print($pdf->output()),
        'berichtsheft-alle-kurse.pdf'
    );
}



    public function render()
    {
        return view('livewire.user.report-book');
    }
}
