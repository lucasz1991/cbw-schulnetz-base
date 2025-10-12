<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use Illuminate\Support\Collection;


class ParticipantsTable extends Component
{
    use WithPagination, WithoutUrlPagination;

    // Basis
    public int $courseId;
    public Course $course;

    // Suche & Sortierung
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';
    public int $perPage = 10;

    // Tagesauswahl (wie CourseDocumentationPanel)
    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    // Abgeleitete Attendance-Map für schnelles Lookup im Blade
    public array $attendanceMap = [];

    // UI-Flags für Prev/Next-Buttons
    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible = false;

    public function mount(int $courseId, ?int $selectedDayId = null): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::findOrFail($courseId);

        // Tagesauswahl initialisieren: explizit -> heute -> erster Tag
        if ($selectedDayId) {
            $this->selectDay($selectedDayId);
        } else {
            $today = now()->toDateString();
            $day   = CourseDay::where('course_id', $courseId)
                ->whereDate('date', $today)
                ->first()
                ?: CourseDay::where('course_id', $courseId)->orderBy('date')->first();

            if ($day) {
                $this->selectedDay   = $day;
                $this->selectedDayId = $day->id;
            }
        }

        $this->rebuildAttendanceMap();
        $this->updatePrevNextFlags();
    }

    // --- Events / Auswahl ---

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

    /** Aus CourseDocumentationPanel gesendet */
    #[On('daySelected')]
    public function setDay(int $dayId): void
    {
        $this->selectDay($dayId);
    }

    public function selectDay(int $courseDayId): void
    {
        $day = CourseDay::where('course_id', $this->courseId)->findOrFail($courseDayId);
        $this->selectedDay   = $day;
        $this->selectedDayId = $day->id;

        $this->rebuildAttendanceMap();
        $this->updatePrevNextFlags();
        $this->resetPage();

        // Wichtig: kein erneutes dispatch('daySelected'), um Event-Ping-Pong zu vermeiden
    }

    protected function updatePrevNextFlags(): void
    {
        if (!$this->selectedDay) {
            $this->selectPreviousDayPossible = false;
            $this->selectNextDayPossible = false;
            return;
        }

        $this->selectPreviousDayPossible = $this->course->dates()
            ->where('date', '<', $this->selectedDay->date)
            ->exists();

        $this->selectNextDayPossible = $this->course->dates()
            ->where('date', '>', $this->selectedDay->date)
            ->exists();
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

    // --- Suche/Sortierung/Pagination ---

    public function updatingSearch() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function sort(string $key): void
    {
        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function getParticipantsProperty()
    {
        // Whitelist akzeptierter Sortierfelder (existierende DB-Spalten!)
        $allowedSorts = ['vorname', 'nachname', 'email', 'created_at'];

        return $this->course->participants()
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(function ($qq) use ($term) {
                    $qq->where('vorname', 'like', $term)
                       ->orWhere('nachname', 'like', $term)
                       ->orWhereRaw("CONCAT_WS(' ', vorname, nachname) LIKE ?", [$term])
                       ->orWhere('email', 'like', $term);
                });
            })
            ->when(true, function ($q) use ($allowedSorts) {
                if ($this->sortBy === 'name') {
                    $q->orderBy('nachname', $this->sortDir)
                      ->orderBy('vorname', $this->sortDir);
                    return;
                }

                $sort = in_array($this->sortBy, $allowedSorts, true) ? $this->sortBy : 'nachname';
                $q->orderBy($sort, $this->sortDir);

                if ($sort === 'nachname') {
                    $q->orderBy('vorname', $this->sortDir);
                }
            })
            ->paginate($this->perPage);
    }

    // --- Attendance-Logik (aus ManageAttendance integriert) ---

    protected function dayOrFail(): CourseDay
    {
        $day = $this->selectedDayId ? CourseDay::find($this->selectedDayId) : null;
        abort_if(!$day, 404, 'Day not found');
        return $day;
    }

    protected function normalizeRow(?array $row): array
    {
        $row = $row ?? [];
        return [
            'present'            => (bool)($row['present'] ?? false),
            'excused'            => (bool)($row['excused'] ?? false),
            'late_minutes'       => (int)($row['late_minutes'] ?? 0),
            'left_early_minutes' => (int)($row['left_early_minutes'] ?? 0),
            'note'               => $row['note'] ?? null,
            'in'                 => data_get($row, 'timestamps.in'),
            'out'                => data_get($row, 'timestamps.out'),
        ];
    }

    protected function rebuildAttendanceMap(): void
    {
        if (!$this->selectedDay) {
            $this->attendanceMap = [];
            return;
        }
        $att = $this->selectedDay->attendance_data ?? [];
        $raw = Arr::get($att, 'participants', []);
        $this->attendanceMap = collect($raw)
            ->map(fn ($row) => $this->normalizeRow($row))
            ->all();
    }


    protected function currentRow(int $participantId): ?array
    {
        return Arr::get($this->selectedDay?->attendance_data, "participants.$participantId");
    }

    protected function apply(int $participantId, array $patch): void
    {
        $day = $this->dayOrFail();
        // Model-Methode aus ManageAttendance erwartet: setAttendance($participantId, $patch)
        $day->setAttendance($participantId, $patch);

        // Lokale Map aktualisieren, ohne komplettes Reload
        $existing = $this->attendanceMap[$participantId] ?? [];
        $merged = array_replace_recursive(
            $this->normalizeRow($existing),
            $patch,
            // timestamps gesondert zusammenführen
            isset($patch['timestamps']) ? [
                'in'  => $patch['timestamps']['in']  ?? ($existing['in'] ?? null),
                'out' => $patch['timestamps']['out'] ?? ($existing['out'] ?? null),
            ] : []
        );
        $this->attendanceMap[$participantId] = $this->normalizeRow($merged);

        // Optional: Day-Model frisch ziehen, wenn serverseitig weitere Anpassungen passieren
        $this->selectedDay?->refresh();
    }

    // ---- Aktionen (Check-in/out, Abwesend, Minuten, Notiz) ----

    public function checkInNow(int $participantId): void
    {
        $now   = Carbon::now();
        $date  = $this->selectedDay?->date?->format('Y-m-d');
        $start = data_get($this->selectedDay?->day_sessions, '1.start', '08:00');

        $late = 0;
        if ($date && $start) {
            $startAt = Carbon::parse("$date $start");
            if ($now->gt($startAt)) {
                $late = $startAt->diffInMinutes($now);
            }
        }

        $this->apply($participantId, [
            'present' => true,
            'excused' => false,
            'late_minutes' => $late,
            'timestamps' => ['in' => $now->toDateTimeString()],
        ]);
    }

    public function markAbsentNow(int $participantId): void
    {
        $row  = $this->currentRow($participantId) ?? [];
        $now  = Carbon::now();
        $date = $this->selectedDay?->date?->format('Y-m-d');

        $endStr = data_get($this->selectedDay?->day_sessions, '4.end')
              ?? $this->selectedDay?->end_time?->format('H:i')
              ?? '16:00';

        $patch = [
            'present' => false,
            'excused' => false,
        ];

        if (!empty($row['present'])) {
            $leftEarly = 0;
            if ($date && $endStr) {
                $endAt = Carbon::parse("$date $endStr");
                if ($now->lt($endAt)) {
                    $leftEarly = $now->diffInMinutes($endAt);
                }
            }
            $patch['left_early_minutes'] = $leftEarly;
            $patch['timestamps'] = ['out' => $now->toDateTimeString()];
        }

        $this->apply($participantId, $patch);
    }

    public function setLateMinutes(int $participantId, $minutes): void
    {
        $this->apply($participantId, ['late_minutes' => max(0, (int)$minutes)]);
    }

    public function setLeftEarlyMinutes(int $participantId, $minutes): void
    {
        $this->apply($participantId, ['left_early_minutes' => max(0, (int)$minutes)]);
    }

    public function setNote(int $participantId, ?string $note): void
    {
        $this->apply($participantId, ['note' => $note]);
    }

    public function checkOutNow(int $participantId): void
    {
        $now  = Carbon::now();
        $date = $this->selectedDay?->date?->format('Y-m-d');

        $endStr = data_get($this->selectedDay?->day_sessions, '4.end')
              ?? $this->selectedDay?->end_time?->format('H:i')
              ?? '16:00';

        $leftEarly = 0;
        if ($date && $endStr) {
            $endAt = Carbon::parse("$date $endStr");
            if ($now->lt($endAt)) {
                $leftEarly = $now->diffInMinutes($endAt);
            }
        }

        $this->apply($participantId, [
            'left_early_minutes' => $leftEarly,
            'timestamps' => ['out' => $now->toDateTimeString()],
        ]);
    }

    public function bulk(string $action): void
    {
        // nur aktuelle Seite
        $ids = $this->participants->getCollection()->pluck('id');
        foreach ($ids as $pid) {
            match ($action) {
                'all_present'  => $this->apply($pid, ['present' => true,  'excused' => false]),
                'all_excused'  => $this->apply($pid, ['excused' => true,  'present' => false]),
                'all_absent'   => $this->apply($pid, ['present' => false, 'excused' => false]),
                'checkin_all'  => $this->checkInNow($pid),
                'checkout_all' => $this->checkOutNow($pid),
                default => null,
            };
        }
    }

    public function getRowsProperty(): Collection
{
    $day = $this->selectedDay;
    if (!$day) {
        return collect();
    }

    $att = $day->attendance_data ?? [];
    $map = Arr::get($att, 'participants', []);

    // IDs aus JSON + Relation zusammenführen
    $allIds = collect(array_keys($map))->map(fn ($id) => (int) $id);

    if ($day->course && method_exists($day->course, 'participants')) {
        $rel = $day->course->participants();
        $qualifiedKey = $rel->getModel()->getQualifiedKeyName(); // z.B. "users.id"
        $allIds = $allIds->merge($rel->pluck($qualifiedKey)->all());
    }

    $allIds = $allIds->map(fn ($id) => (int) $id)->unique()->values();

    // Teilnehmer laden
    $participants = collect();
    if ($day->course && method_exists($day->course, 'participants') && $allIds->isNotEmpty()) {
        $rel = $day->course->participants();
        $qualifiedKey = $rel->getModel()->getQualifiedKeyName();
        $participants = $rel->whereIn($qualifiedKey, $allIds)->get()->keyBy('id');
    }

    $rows = $allIds->map(function (int $pid) use ($participants, $map) {
        return [
            'id'   => $pid,
            'user' => $participants[$pid] ?? null,
            'data' => $this->normalizeRow($map[$pid] ?? null),
        ];
    });

    return $rows
        ->sortBy(fn ($r) => strtolower($r['user']->nachname ?? ('zzzz_'.$r['id'])))
        ->values();
}

/** Tages-Statistik auf Basis von $rows */
public function getStatsProperty(): array
{
    $rows    = $this->rows;
    $present = $rows->where('data.present', true)->count();
    $excused = $rows->where('data.excused', true)->count();
    $late    = $rows->filter(fn ($r) => ((int)($r['data']['late_minutes'] ?? 0)) > 0)->count();
    $total   = max($rows->count(), 0);
    $absent  = $total - $present - $excused;

    return compact('present', 'excused', 'late', 'absent', 'total');
}
    // --- Render ---

    public function render()
    {
        // Falls sich Daten seit dem letzten Tick änderten, Flags sauber halten
        $this->updatePrevNextFlags();

        return view('livewire.tutor.courses.participants-table', [
            'participants' => $this->participants, 
            'rows'         => $this->rows,
            'stats'        => $this->stats,
            'selectedDay'  => $this->selectedDay,
            'selectedDayId'=> $this->selectedDayId,
            'selectPreviousDayPossible' => $this->selectPreviousDayPossible,
            'selectNextDayPossible'     => $this->selectNextDayPossible,
        ]);
    }
}
