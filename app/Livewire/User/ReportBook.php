<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\Attributes\On;
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
use Livewire\Attributes\Url;


class ReportBook extends Component
{
    /** Optional: Maßnahme-Kontext */
    public ?string $massnahmeId = null;

/** Kurs-/Tag-Auswahl */
public array $courses = [];              // [{id,title}, ...]

// Querystring: ?course=123
#[Url(as: 'course', history: true)]
public ?int $selectedCourseId = null;

public ?string $selectedCourseName = null;

public array $courseDays = [];           // [{id,date,label}, ...]

// Querystring: ?day=456
#[Url(as: 'day', history: true)]
public ?int $selectedCourseDayId = null; // aktueller Kurs-Tag

    /** Inhalt */
    public ?string $title = null;            // falls später Überschrift genutzt wird
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

    protected $listeners = [ 'reportbook.entry.updated' => 'loadCurrentEntry' ];

public function mount(): void
{
    // Kurse des Users laden
    $this->courses = $this->fetchUserCourses();

    // 1) Kurs aus URL prüfen, sonst fallback
    if (
        !$this->selectedCourseId ||
        !collect($this->courses)->contains('id', (int) $this->selectedCourseId)
    ) {
        $this->selectedCourseId = $this->courses[0]['id'] ?? null;
    }

    $this->selectedCourseName = $this->courseById((int) $this->selectedCourseId)['title'] ?? '—';

    // 2) Tage für Kurs laden
    $this->loadCourseDays();

    // 3) Day aus URL prüfen, sonst fallback
    $validDayIds = collect($this->courseDays)->pluck('id')->map(fn ($v) => (int) $v)->all();

    if (
        !$this->selectedCourseDayId ||
        !in_array((int) $this->selectedCourseDayId, $validDayIds, true)
    ) {
        $this->selectedCourseDayId = $this->guessInitialCourseDayId();
    }

    // 4) Einträge laden
    $this->loadCurrentEntry();
    $this->loadRecent();
}

    /* ======================= Kursliste & Tage ======================= */

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
                DB::raw('SUM(CASE WHEN rbe.status >= 1 THEN 1 ELSE 0 END) AS days_finished'),
            ])
            ->groupBy(
                'courses.id', 'courses.title', 'courses.klassen_id',
                'courses.planned_start_date', 'courses.planned_end_date'
            )
            ->orderBy('courses.planned_start_date', 'asc');

        $today = Carbon::today();

        return collect($q->get())->map(function ($c) use ($today) {
            $start = $c->planned_start_date ? Carbon::parse($c->planned_start_date) : null;
            $end   = $c->planned_end_date   ? Carbon::parse($c->planned_end_date)   : null;
            $phase = 'unbekannt';
            $phaseColor = 'slate';

            if ($start && $end) {
                if ($end->lt($today)) {
                    $phase = 'beendet';
                    $phaseColor = 'gray';
                } elseif ($start->gt($today)) {
                    $phase = 'geplant';
                    $phaseColor = 'amber';
                } else {
                    $phase = 'läuft';
                    $phaseColor = 'blue';
                }
            }

            $total     = (int) $c->days_total;
            $withEntry = (int) $c->days_with_entry;
            $finished  = (int) $c->days_finished;
            $missing   = max(0, $total - $withEntry);
            $hasAllFinished = ($total > 0 && $finished === $total);

            if ($total === 0) {
                $ampel = ['label' => 'Keine Kurstage', 'color' => 'slate', 'info' => null];
            } elseif ($hasAllFinished) {
                $ampel = ['label' => 'Alle Tage fertig', 'color' => 'green', 'info' => "$finished/$total"];
            } elseif ($withEntry === $total) {
                $ampel = ['label' => 'Alle belegt (noch nicht fertig)', 'color' => 'amber', 'info' => "$finished/$total"];
            } else {
                $ampel = ['label' => "$missing Tag(e) ohne Eintrag", 'color' => 'red', 'info' => "$finished/$total"];
            }

            return [
                'id'                => $c->id,
                'klassen_id'        => $c->klassen_id,
                'title'             => $c->title ?? ('Kurs #' . $c->id),
                'planned_start_date'=> $c->planned_start_date,
                'planned_end_date'  => $c->planned_end_date,
                'days_total'        => $total,
                'days_with_entry'   => $withEntry,
                'days_draft'        => (int) $c->days_draft,
                'days_finished'     => $finished,
                'days_missing'      => $missing,
                'phase'             => $phase,
                'phase_color'       => $phaseColor,
                'ampel'             => $ampel,
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
            ->orderBy('cd.date')
            ->orderBy('cd.start_time')
            ->get([
                'cd.id',
                'cd.date',
                'cd.start_time',
                'cd.end_time',
                'cd.notes',
                'rbe.status',
            ]);

        $this->courseDays = $days->map(function ($d) {
            $date  = Carbon::parse($d->date)->toDateString();
            $label = Carbon::parse($d->date)->format('d.m.Y');

            $status = is_null($d->status) ? null : (int) $d->status;
            $dot = match (true) {
                $status === null => ['color' => 'gray',  'title' => 'Kein Eintrag'],
                $status === 0    => ['color' => 'amber', 'title' => 'Entwurf'],
                $status >= 1     => ['color' => 'green', 'title' => 'Fertig'],
            };

            return [
                'id'          => $d->id,
                'date'        => $date,
                'label'       => $label,
                'status'      => $status,
                'dot'         => $dot,
                'hasTutorDoc' => !empty($d->notes),
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

    // wichtig: old day aus URL entfernen, weil der zum neuen Kurs nicht passt
    $this->selectedCourseDayId = null;

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
    $courseDayId = (int) $courseDayId;

    if ($this->selectedCourseDayId === $courseDayId) return;

    // Validieren: Tag muss im aktuellen courseDays-Array existieren
    $validDayIds = array_map(fn ($d) => (int) ($d['id'] ?? 0), $this->courseDays);

    if (!in_array($courseDayId, $validDayIds, true)) {
        $this->dispatch('toast', type: 'warning', message: 'Kurstag ist für diesen Kurs nicht gültig.');
        return;
    }

    $this->selectedCourseDayId = $courseDayId;

    // optional aber sinnvoll: beim Tagwechsel keine "alte" EntryId mitschleppen
    $this->reportBookEntryId = null;

    $this->loadCurrentEntry();
}

    public function updated($name, $value): void
    {
        if (in_array($name, ['title', 'text'], true)) {
            $this->recomputeFlags();
        }
    }

protected function reloadForCurrentCourse(): void
{
    $selectedCourseId    = $this->selectedCourseId;
    $selectedCourseDayId = $this->selectedCourseDayId;

    $this->courses = $this->fetchUserCourses();

    // Kurs validieren
    if (!$selectedCourseId || !collect($this->courses)->contains('id', (int) $selectedCourseId)) {
        $this->selectedCourseId = $this->courses[0]['id'] ?? null;
    } else {
        $this->selectedCourseId = (int) $selectedCourseId;
    }

    $this->selectedCourseName = $this->courseById((int) $this->selectedCourseId)['title'] ?? '—';

    $this->loadCourseDays();

    // Day validieren
    $validDayIds = collect($this->courseDays)->pluck('id')->map(fn ($v) => (int) $v)->all();

    if ($selectedCourseDayId && in_array((int) $selectedCourseDayId, $validDayIds, true)) {
        $this->selectedCourseDayId = (int) $selectedCourseDayId;
    } else {
        $this->selectedCourseDayId = $this->guessInitialCourseDayId();
    }

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

        // Wenn der Eintrag wieder zum Entwurf wird → bestehende Teilnehmer-Signatur löschen
        $book = ReportBookModel::with('files')->find($this->reportBookId);

        if ($book) {
            if (method_exists($book, 'participantSignatureFile')) {
                $sig = $book->participantSignatureFile();
            } else {
                $sig = $book->files()
                    ->where('type', 'sign_reportbook_participant')
                    ->latest()
                    ->first();
            }

            if ($sig) {
                $sig->delete();
            }
        }


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

        // Speichern vor Statuswechsel / vor Signatur
        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'submitted_at' => now(),
        ])->save();

        $this->reportBookEntryId = $entry->id;

        $needsSignature = $this->checkCourseCompletionAndOpenSignature();

        if (!$needsSignature) {
            $entry->fill([
                'status' => 1,
            ])->save();

            $this->status   = 1;
            $this->hasDraft = false;

            $this->initialHash = $this->curHash();
            $this->recomputeFlags();
            $this->reloadForCurrentCourse();
            $this->dispatch('toast', type: 'success', message: 'Eintrag fertiggestellt.');
        }
    }

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

        $book = ReportBookModel::with(['course.days', 'files'])->find($this->reportBookId);
        if (!$book || !$book->course) {
            return false;
        }

        // Alle Kurstage des Kurses
        $totalDays = $book->course->days->count();
        if ($totalDays === 0) {
            return false;
        }

        // Aktueller Tag muss zum Kurs gehören
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

        // Dieser Tag wäre mit diesem Submit der letzte
        if ($finishedDays + 1 !== $totalDays) {
            return false;
        }

        // Gibt es bereits eine Teilnehmer-Signatur?
        $hasSignature = false;

        if (method_exists($book, 'participantSignatureFile')) {
            $hasSignature = (bool) $book->participantSignatureFile();
        } else {
            $hasSignature = $book->files()
                ->where('type', 'sign_reportbook_participant')
                ->exists();
        }

        if ($hasSignature) {
            return false;
        }

        // Kontext-Name für den Dialog
        $courseTitle = $book->course->title ?? 'Kurs';
        $klasse      = $book->course->klassen_id ?? null;
        $contextName = $klasse ? "{$courseTitle} – {$klasse}" : $courseTitle;

        // Generisches Signature-Form öffnen
        $this->dispatch('openSignatureForm', [
            'fileableType' => ReportBookModel::class,
            'fileableId'   => $book->id,
            'fileType'     => 'sign_reportbook_participant',
            'label'        => 'Berichtsheft abschließen',
            'signForName'  => 'Berichtsheft',
            'contextName'  => $contextName,
            'confirmText'  => "Ich bestätige, dass alle Angaben in meinem <strong>Berichtsheft<br>({$contextName})</strong><br>vollständig und wahrheitsgemäß sind.",
        ]);

        return true;
    }

    /* ======================= Signature Events (neuer Flow) ======================= */

    #[On('signatureCompleted')]
    public function handleSignatureCompleted(array $payload): void
    {
        $fileableType = data_get($payload, 'fileableType');
        $fileType     = data_get($payload, 'fileType');
        $fileableId   = (int) data_get($payload, 'fileableId');

        // Nur reagieren, wenn es UNSER Berichtsheft-Signaturtyp ist
        if (
            $fileableType !== ReportBookModel::class ||
            $fileType !== 'sign_reportbook_participant' ||
            !$fileableId
        ) {
            return;
        }

        $book = ReportBookModel::with('course')->find($fileableId);
        if (!$book || $book->user_id !== Auth::id()) {
            return;
        }

        if (!$this->selectedCourseDayId) {
            return;
        }

        $day = CourseDay::find($this->selectedCourseDayId);
        if (!$day || $day->course_id !== $book->course_id) {
            return;
        }

        // Eintrag holen (per ID oder Kombination)
        if ($this->reportBookEntryId) {
            $entry = ReportBookEntry::find($this->reportBookEntryId);
        } else {
            $entry = ReportBookEntry::where('report_book_id', $book->id)
                ->where('course_day_id', $day->id)
                ->first();
        }

        if (!$entry) {
            $this->dispatch('toast', type: 'warning', message: 'Kein Eintrag zum Unterschreiben gefunden.');
            return;
        }

        // Finalen Status setzen
        $entry->status = 1;
        if (!$entry->submitted_at) {
            $entry->submitted_at = now();
        }
        $entry->save();

        $this->reportBookId = $book->id;
        $this->status       = 1;
        $this->hasDraft     = false;

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();
        $this->reloadForCurrentCourse();

        $this->dispatch('toast', type: 'success', message: 'Berichtsheft für diesen Kurs wurde unterschrieben.');
    }

    #[On('signatureAborted')]
    public function handleSignatureAborted(array $payload = []): void
    {
        // Hier musst du nichts löschen; beim Berichtsheft wird nur unterschrieben,
        // es wird kein eigener Zwischen-Datensatz erzeugt.
        // Optional: Info ausgeben oder einfach still ignorieren.
        // $this->dispatch('toast', type: 'info', message: 'Signatur abgebrochen.');
        return;
    }

    /* ======================= Loader / Helper ======================= */

    protected function loadCurrentEntry(): void
    {
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

        $book = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->when($this->massnahmeId, fn ($q) => $q->where('massnahme_id', $this->massnahmeId))
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
            ->get(['entry_date', 'text', 'status'])
            ->map(fn ($r) => [
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
        $this->selectCourse((int) $ids[$prevIdx]);
    }

    public function selectNextCourse(): void
    {
        if (empty($this->courses)) return;

        $ids = array_column($this->courses, 'id');
        $idx = array_search($this->selectedCourseId, $ids, true);

        $nextIdx = is_int($idx) ? ($idx + 1) % count($ids) : 0;
        $this->selectCourse((int) $ids[$nextIdx]);
    }

    public function selectPrevDay(): void
    {
        if (empty($this->courseDays) || !$this->selectedCourseDayId) return;

        $ids = array_map(fn ($d) => (int) $d['id'], $this->courseDays);
        $idx = array_search((int) $this->selectedCourseDayId, $ids, true);

        $prevIdx = is_int($idx) ? ($idx - 1 + count($ids)) % count($ids) : 0;
        $this->selectCourseDay((int) $ids[$prevIdx]);
    }

    public function selectNextDay(): void
    {
        if (empty($this->courseDays) || !$this->selectedCourseDayId) return;

        $ids = array_map(fn ($d) => (int) $d['id'], $this->courseDays);
        $idx = array_search((int) $this->selectedCourseDayId, $ids, true);

        $nextIdx = is_int($idx) ? ($idx + 1) % count($ids) : 0;
        $this->selectCourseDay((int) $ids[$nextIdx]);
    }

    protected function courseById(int $id): ?array
    {
        foreach ($this->courses as $c) {
            if ((int) ($c['id'] ?? 0) === $id) {
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

        $day = CourseDay::find($this->selectedCourseDayId, ['id', 'date', 'notes']);
        if (!$day) {
            $this->dispatch('toast', type: 'warning', message: 'Kurstag nicht gefunden.');
            return;
        }

        $doc = trim((string) ($day->notes ?? ''));
        if ($doc === '') {
            $this->dispatch('toast', type: 'info', message: 'Keine Dozenten-Dokumentation vorhanden.');
            return;
        }

        if (trim($this->text) !== '') {
            $this->text = rtrim($this->text) . "\n\n" . $doc;
        } else {
            $this->text = $doc;
        }

        $this->ensureReportBookId();

        $entry = ReportBookEntry::firstOrNew([
            'report_book_id' => $this->reportBookId,
            'course_day_id'  => $day->id,
        ]);

        $entry->fill([
            'entry_date'   => $day->date,
            'text'         => $this->text,
            'status'       => 0,
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

    /* ======================= Exporte ======================= */

public function exportReportEntry(): ?StreamedResponse
{
    if (!$this->selectedCourseId || !$this->selectedCourseDayId) {
        $this->dispatch('toast', type: 'warning', message: 'Kurs und Kurstag auswählen.');
        return null;
    }

    $book = ReportBookModel::with([
            'course',
            'files' => fn ($q) => $q->whereIn('type', [
                'sign_reportbook_participant',
                'sign_reportbook_trainer',
            ])->orderByDesc('id'),
        ])
        ->where('user_id', Auth::id())
        ->where('course_id', $this->selectedCourseId)
        ->when($this->massnahmeId, fn ($q) => $q->where('massnahme_id', $this->massnahmeId))
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

    $participantSignatureFile = $book->signature('sign_reportbook_participant');
    $trainerSignatureFile     = $book->signature('sign_reportbook_trainer');

    $participantSignatureUrl = $participantSignatureFile ? $participantSignatureFile->getEphemeralPublicUrl(10) : null;
    $trainerSignatureUrl     = $trainerSignatureFile ? $trainerSignatureFile->getEphemeralPublicUrl(10) : null;

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'                    => 'single',
        'entry'                   => $entry,
        'course'                  => $book->course,
        'user'                    => Auth::user(),
        'title'                   => 'Bericht ' . $entry->entry_date->format('d.m.Y'),
        'participantSignatureFile' => $participantSignatureFile,
        'trainerSignatureFile'     => $trainerSignatureFile,
        'participantSignatureUrl'  => $participantSignatureUrl,
        'trainerSignatureUrl'      => $trainerSignatureUrl,
    ]);

    return response()->streamDownload(
        fn () => print($pdf->output()),
        'bericht-' . $entry->entry_date->format('Y-m-d') . '.pdf'
    );
}

public function exportReportModule(): ?StreamedResponse
{
    $book = ReportBookModel::with([
            'course',
            'entries' => fn ($q) => $q->orderBy('entry_date'),
            'files' => fn ($q) => $q->whereIn('type', [
                'sign_reportbook_participant',
                'sign_reportbook_trainer',
            ])->orderByDesc('id'),
        ])
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

    $participantSignatureFile = $book->signature('sign_reportbook_participant');
    $trainerSignatureFile     = $book->signature('sign_reportbook_trainer');

    $participantSignatureUrl = $participantSignatureFile ? $participantSignatureFile->getEphemeralPublicUrl(10) : null;
    $trainerSignatureUrl     = $trainerSignatureFile ? $trainerSignatureFile->getEphemeralPublicUrl(10) : null;

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'                    => 'module',
        'entries'                 => $book->entries,
        'course'                  => $book->course,
        'user'                    => Auth::user(),
        'title'                   => 'Berichtsheft – ' . ($book->course->klassen_id ?? $book->course->title ?? ''),
        'participantSignatureFile' => $participantSignatureFile,
        'trainerSignatureFile'     => $trainerSignatureFile,
        'participantSignatureUrl'  => $participantSignatureUrl,
        'trainerSignatureUrl'      => $trainerSignatureUrl,
    ]);

    return response()->streamDownload(
        fn () => print($pdf->output()),
        'berichtsheft-' . ($book->course->klassen_id ?? $book->course->id) . '.pdf'
    );
}

public function exportReportAll(): ?StreamedResponse
{
    $books = ReportBookModel::with([
            'course',
            'entries' => fn ($q) => $q->orderBy('entry_date'),
            'files' => fn ($q) => $q->whereIn('type', [
                'sign_reportbook_participant',
                'sign_reportbook_trainer',
            ])->orderByDesc('id'),
        ])
        ->where('user_id', Auth::id())
        ->when($this->massnahmeId, fn ($q) => $q->where('massnahme_id', $this->massnahmeId))
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

        $pFile = $book->signature('sign_reportbook_participant');
        $tFile = $book->signature('sign_reportbook_trainer');
        $book->participantSignatureFile = $pFile;
        $book->trainerSignatureFile     = $tFile;
        $book->participantSignatureUrl = $pFile ? $pFile->getEphemeralPublicUrl(10) : null;
        $book->trainerSignatureUrl     = $tFile ? $tFile->getEphemeralPublicUrl(10) : null;
    }

    $pdf = Pdf::loadView('pdf.report-book', [
        'mode'  => 'all',
        'books' => $books,
        'user'  => Auth::user(),
        'title' => 'Berichtsheft – Alle Kurse',
    ]);

    return response()->streamDownload(
        fn () => print($pdf->output()),
        'berichtsheft-alle-kurse.pdf'
    );
}


    public function getStatusAttribute(): int
    {
        $reportBook = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->first();

        return $reportBook ? $reportBook->status : 0;
    }

    public function getAreAllReportBooksReviewedProperty(): bool
    {
        $reportBook = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->first();

        return $reportBook ? $reportBook->areAllReportBooksReviewed : false;
    }

    public function getIsReportBookReviewedProperty(): bool
    {
        $reportBook = ReportBookModel::where('user_id', Auth::id())
            ->where('course_id', $this->selectedCourseId)
            ->first();

        return $reportBook ? $reportBook->isReportBookReviewed() : false;
    }

    public function render()
    {
        return view('livewire.user.report-book')->layout("layouts.app");
    }
}
