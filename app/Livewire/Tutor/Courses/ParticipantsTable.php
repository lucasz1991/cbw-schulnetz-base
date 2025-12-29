<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;

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

    // Tagesauswahl
    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    /**
     * Source-of-truth für UI Rendering pro Participant.
     * Wird beim Load komplett rebuilt + bei apply/saveOne gezielt aktualisiert.
     */
    public array $attendanceMap = [];

    // UI-Flags für Prev/Next
    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible     = false;

    // Globales Laden (nur für "Load vom UVS")
    public bool $isLoadingApi = false;

    // Dirty (lokal geändert, noch nicht synced)
    public bool $isDirty = false;

    // Wrapper Inputs (für wire:target-fähige save* Actions)
    public array $arriveInput = [];
    public array $leaveInput  = [];
    public array $noteInput   = [];

    public function mount(int $courseId, ?int $selectedDayId = null): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::findOrFail($courseId);

        if ($selectedDayId) {
            $this->selectedDayId = $selectedDayId;
            $this->selectedDay   = CourseDay::where('course_id', $courseId)->findOrFail($selectedDayId);
        } else {
            $today = now('Europe/Berlin')->toDateString();

            $day = CourseDay::where('course_id', $courseId)
                ->whereDate('date', $today)
                ->first()
                ?: CourseDay::where('course_id', $courseId)->orderBy('date')->first();

            if ($day) {
                $this->selectedDay   = $day;
                $this->selectedDayId = $day->id;
            }
        }

        $this->loadAttendance();

        Log::info('attendance sample', [
            'sample' => data_get($this->selectedDay?->attendance_data, 'participants.' . array_key_first(data_get($this->selectedDay?->attendance_data, 'participants', []))),
        ]);

        $this->updatePrevNextFlags();
    }

    /**
     * Pull-only Load (UVS ist Master).
     * Blockt global -> ok für Öffnen / Day-Wechsel.
     */
    protected function loadAttendance(): void
    {
        if (! $this->selectedDayId) return;

        $day = $this->dayOrFail();
        $this->isLoadingApi = true;

        try {
            /** @var CourseDayAttendanceSyncService $service */
            $service = app(CourseDayAttendanceSyncService::class);

            $service->loadFromRemote($day);

            $day->refresh();
            $this->selectedDay   = $day;
            $this->selectedDayId = $day->id;

            // UI-Source-of-truth rebuilden
            $this->rebuildAttendanceMap();

            $this->isDirty = false;
        } catch (\Throwable $e) {
            Log::error('ParticipantsTable.loadAttendance: Fehler', [
                'day_id' => $day->id ?? null,
                'error'  => $e->getMessage(),
            ]);
        } finally {
            $this->isLoadingApi = false;
        }
    }

    /**
     * 1) lokal patchen (dirty)
     * 2) sofort row-weise zu UVS syncen (wie Results)
     */
    public function applyAndSaveOne(int $participantId, array $patch = []): void
    {
        $this->apply($participantId, $patch);
        $this->saveOne($participantId);
         $this->loadAttendance();
    }

    /**
     * Row-Sync zu UVS – KEIN global isLoadingApi.
     * Wichtig: nach Sync wird attendanceMap + wrapper Inputs aktualisiert,
     * damit UI sofort den frischen Stand zeigt (ohne loadAttendance()).
     */
    public function saveOne(int $participantId): void
    {
        $day = $this->dayOrFail();

        try {
            /** @var CourseDayAttendanceSyncService $service */
            $service = app(CourseDayAttendanceSyncService::class);

            $ok = $service->syncToRemote($day, [$participantId]);

            // DB/Model frisch holen, aber UI basiert auf attendanceMap (wird gleich aktualisiert)
            $day->refresh();
            $this->selectedDay = $day;

            $fresh = data_get($day->attendance_data, "participants.$participantId", []);
            $freshNormalized = $this->normalizeRow($fresh);

            // ✅ UI sofort updaten (Source-of-truth)
            $this->attendanceMap[$participantId] = $freshNormalized;

            // Wrapper updaten (Inputs zeigen saved Stand)
            $this->arriveInput[$participantId] = data_get($fresh, 'arrived_at');
            $this->leaveInput[$participantId]  = data_get($fresh, 'left_at');
            $this->noteInput[$participantId]   = data_get($fresh, 'note');

            $this->isDirty = false;

            if (! $ok) {
                $this->dispatch('notify', type: 'error', message: "UVS-Sync fehlgeschlagen (#{$participantId}).");
            }
        } catch (\Throwable $e) {
            Log::error('ParticipantsTable.saveOne: Fehler beim UVS-Sync', [
                'day_id'         => $day->id ?? null,
                'participant_id' => $participantId,
                'error'          => $e->getMessage(),
            ]);

            $this->dispatch('notify', type: 'error', message: "Fehler beim Speichern (#{$participantId}).");
        }
    }

    #[On('calendarEventClick')]
    public function handleCalendarEventClick(...$args): void
    {
        $first = $args[0] ?? null;
        $id    = is_array($first) ? (int) data_get($first, 'id') : (int) $first;

        if ($id > 0) $this->selectDay($id);
    }

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

        $this->loadAttendance();

        $this->updatePrevNextFlags();
        $this->resetPage();
    }

    protected function updatePrevNextFlags(): void
    {
        if (! $this->selectedDay) {
            $this->selectPreviousDayPossible = false;
            $this->selectNextDayPossible     = false;
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
        if (! $this->selectedDay) return;

        $prev = $this->course->dates()
            ->where('date', '<', $this->selectedDay->date)
            ->orderByDesc('date')
            ->first();

        if ($prev) $this->selectDay($prev->id);
    }

    public function selectNextDay(): void
    {
        if (! $this->selectedDay) return;

        $next = $this->course->dates()
            ->where('date', '>', $this->selectedDay->date)
            ->orderBy('date')
            ->first();

        if ($next) $this->selectDay($next->id);
    }

    // ---- Suche/Sortierung/Pagination ----

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function sort(string $key): void
    {
        $this->sortDir = ($this->sortBy === $key)
            ? ($this->sortDir === 'asc' ? 'desc' : 'asc')
            : 'asc';

        $this->sortBy = $key;
        $this->resetPage();
    }

    public function getParticipantsProperty()
    {
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
                      ->orderBy('vorname',  $this->sortDir);
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

    // ---- Attendance-Logik ----

    protected function dayOrFail(): CourseDay
    {
        if ($this->selectedDay && $this->selectedDayId && (int) $this->selectedDay->id === (int) $this->selectedDayId) {
            return $this->selectedDay;
        }

        $day = $this->selectedDayId
            ? CourseDay::where('course_id', $this->courseId)->find($this->selectedDayId)
            : null;

        abort_if(! $day, 404, 'Day not found');

        $this->selectedDay = $day;
        return $day;
    }

    protected function normalizeRow(?array $row): array
    {
        $row = $row ?? [];

        $arr = $row['arrived_at'] ?? null;
        $lef = $row['left_at'] ?? null;

        $hasTime = (!empty($arr) && $arr !== '00:00') || (!empty($lef) && $lef !== '00:00');

        $present = array_key_exists('present', $row)
            ? (bool) $row['present']
            : $hasTime;

        return [
            'present'            => $present,
            'excused'            => (bool)($row['excused'] ?? false),
            'late_minutes'       => (int)($row['late_minutes'] ?? 0),
            'left_early_minutes' => (int)($row['left_early_minutes'] ?? 0),
            'note'               => $row['note'] ?? null,
            'in'                 => data_get($row, 'timestamps.in'),
            'out'                => data_get($row, 'timestamps.out'),
            'arrived_at'         => $arr,
            'left_at'            => $lef,
        ];
    }

    protected function rebuildAttendanceMap(): void
    {
        if (! $this->selectedDay) {
            $this->attendanceMap = [];
            $this->arriveInput = [];
            $this->leaveInput  = [];
            $this->noteInput   = [];
            return;
        }

        $att = $this->selectedDay->attendance_data ?? [];
        $raw = Arr::get($att, 'participants', []);

        $this->attendanceMap = collect($raw)
            ->map(fn ($row) => $this->normalizeRow($row))
            ->all();

        foreach ($this->attendanceMap as $pid => $row) {
            $this->arriveInput[$pid] = $row['arrived_at'] ?? null;
            $this->leaveInput[$pid]  = $row['left_at'] ?? null;
            $this->noteInput[$pid]   = $row['note'] ?? null;
        }
    }

    protected function currentRow(int $participantId): ?array
    {
        return Arr::get($this->selectedDay?->attendance_data, "participants.$participantId");
    }

    /**
     * Lokal patchen + attendanceMap sofort updaten,
     * damit UI sofort den geänderten Stand zeigt (noch bevor Sync durch ist).
     */
    protected function apply(int $participantId, array $patch): void
    {
        $day = $this->dayOrFail();

        $patch['state'] = 'dirty';

        // Model patchen
        $day->setAttendance($participantId, $patch);

        $this->selectedDay = $day;
        $this->isDirty = true;

        // attendanceMap patchen (UI)
        $existing = $this->attendanceMap[$participantId] ?? $this->normalizeRow($this->currentRow($participantId) ?? []);
        $merged   = array_replace_recursive($existing, $patch);

        if (isset($patch['timestamps'])) {
            $merged['in']  = $patch['timestamps']['in']  ?? ($existing['in']  ?? null);
            $merged['out'] = $patch['timestamps']['out'] ?? ($existing['out'] ?? null);
        }

        if (array_key_exists('arrived_at', $patch)) $merged['arrived_at'] = $patch['arrived_at'];
        if (array_key_exists('left_at',    $patch)) $merged['left_at']    = $patch['left_at'];

        $this->attendanceMap[$participantId] = $this->normalizeRow($merged);

        // wrapper Inputs aktualisieren
        if (array_key_exists('arrived_at', $patch)) $this->arriveInput[$participantId] = $patch['arrived_at'];
        if (array_key_exists('left_at',    $patch)) $this->leaveInput[$participantId]  = $patch['left_at'];
        if (array_key_exists('note',       $patch)) $this->noteInput[$participantId]   = $patch['note'];
    }

    // ---- Actions (wie gehabt) ----

    public function checkInNow(int $participantId): void
    {
        $now = Carbon::now('Europe/Berlin');
        [$start] = $this->plannedTimesForSelectedDay();

        $late = 0;
        if ($start && $now->gt($start)) {
            $late = $start->diffInMinutes($now);
        }

        $this->applyAndSaveOne($participantId, [
            'present'      => true,
            'excused'      => false,
            'late_minutes' => $late,
            'timestamps'   => ['in' => $now->toDateTimeString()],
        ]);
    }

    public function checkOutNow(int $participantId): void
    {
        $now = Carbon::now('Europe/Berlin');
        [, $end] = $this->plannedTimesForSelectedDay();

        $leftEarly = 0;
        if ($end && $now->lt($end)) {
            $leftEarly = $now->diffInMinutes($end);
        }

        $this->applyAndSaveOne($participantId, [
            'left_early_minutes' => $leftEarly,
            'timestamps'         => ['out' => $now->toDateTimeString()],
        ]);
    }

    public function markPresent(int $participantId): void
    {
        $this->applyAndSaveOne($participantId, [
            'present' => true,
            'excused' => false,
        ]);
    }

    public function markAbsent(int $participantId): void
    {
        $this->applyAndSaveOne($participantId, [
            'present' => false,
            'excused' => false,
        ]);
    }

    public function markAbsentNow(int $participantId): void
    {
        $row = $this->currentRow($participantId) ?? [];
        $now = Carbon::now('Europe/Berlin');

        [, $end] = $this->plannedTimesForSelectedDay();

        $patch = [
            'present' => false,
            'excused' => false,
        ];

        if (!empty($row['present']) && $end && $now->lt($end)) {
            $patch['left_early_minutes'] = $now->diffInMinutes($end);
            $patch['timestamps'] = ['out' => $now->toDateTimeString()];
        }

        $this->applyAndSaveOne($participantId, $patch);
    }

    public function setLateMinutes(int $participantId, $minutes): void
    {
        $this->applyAndSaveOne($participantId, [
            'late_minutes' => max(0, (int) $minutes),
        ]);
    }

    public function setLeftEarlyMinutes(int $participantId, $minutes): void
    {
        $this->applyAndSaveOne($participantId, [
            'left_early_minutes' => max(0, (int) $minutes),
        ]);
    }

    public function setArrivalTime(int $participantId, ?string $hhmm): void
    {
        $time = $this->normalizeTime($hhmm);
        $arr  = $this->toCarbonOnSelectedDate($time);

        [$start] = $this->plannedTimesForSelectedDay();
        $late = 0;
        if ($start && $arr && $arr->greaterThan($start)) {
            $late = $start->diffInMinutes($arr);
        }

        $this->applyAndSaveOne($participantId, [
            'arrived_at'   => $time,
            'late_minutes' => $late,
            'present'      => true,
        ]);
    }

    public function setLeaveTime(int $participantId, ?string $hhmm): void
    {
        $time = $this->normalizeTime($hhmm);
        $out  = $this->toCarbonOnSelectedDate($time);

        [, $end] = $this->plannedTimesForSelectedDay();
        $early = 0;
        if ($end && $out && $out->lessThan($end)) {
            $early = $out->diffInMinutes($end);
        }

        $this->applyAndSaveOne($participantId, [
            'left_at'            => $time,
            'left_early_minutes' => $early,
            'present'            => true,
        ]);
    }

    public function setNote(int $participantId, ?string $note): void
    {
        $this->applyAndSaveOne($participantId, [
            'note' => $note,
        ]);
    }

    // ---- Wrapper (1 Param → perfekt für wire:target) ----

    public function saveArrival(int $participantId): void
    {
        $time = $this->arriveInput[$participantId] ?? null;
        $this->setArrivalTime($participantId, $time);
    }

    public function saveLeave(int $participantId): void
    {
        $time = $this->leaveInput[$participantId] ?? null;
        $this->setLeaveTime($participantId, $time);
    }

    public function saveNote(int $participantId): void
    {
        $note = $this->noteInput[$participantId] ?? null;
        $this->setNote($participantId, $note);
    }

    /**
     * Setzt Zeiten & Ableitungen für 1 Participant zurück
     * und synced sofort (wie deine anderen Actions).
     */
    public function clearTimes(int $participantId): void
    {
        $row = $this->currentRow($participantId) ?? [];
        $patch = [
            'arrived_at'         => null,
            'left_at'            => null,
            'late_minutes'       => 0,
            'left_early_minutes' => 0,
            'timestamps'         => [
                'in'  => null,
                'out' => null,
            ],
        ];
        $this->applyAndSaveOne($participantId, $patch);
    }

    public function bulk(string $action): void
    {
        $ids = $this->participants->getCollection()->pluck('id');

        foreach ($ids as $pid) {
            match ($action) {
                'all_present'  => $this->markPresent((int)$pid),
                'all_excused'  => $this->applyAndSaveOne((int)$pid, ['excused' => true,  'present' => false]),
                'all_absent'   => $this->markAbsent((int)$pid),
                'checkin_all'  => $this->checkInNow((int)$pid),
                'checkout_all' => $this->checkOutNow((int)$pid),
                default => null,
            };
        }
    }

    public function getRowsProperty(): Collection
    {
        $day = $this->selectedDay;
        if (! $day) return collect();

        $att = $day->attendance_data ?? [];
        $map = Arr::get($att, 'participants', []);

        // IDs aus remote/local
        $allIds = collect(array_keys($map))->map(fn ($id) => (int) $id);

        if ($day->course && method_exists($day->course, 'participants')) {
            $rel          = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName();
            $allIds       = $allIds->merge($rel->pluck($qualifiedKey)->all());
        }

        $allIds = $allIds->map(fn ($id) => (int) $id)->unique()->values();
foreach ($allIds as $pid) {
    $this->arriveInput[$pid] ??= null;
    $this->leaveInput[$pid]  ??= null;
    $this->noteInput[$pid]   ??= null;
}
        $participants = collect();
        if ($day->course && method_exists($day->course, 'participants') && $allIds->isNotEmpty()) {
            $rel          = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName();
            $participants = $rel->whereIn($qualifiedKey, $allIds)->get()->keyBy('id');
        }

        // ✅ Datenquelle für UI = attendanceMap (sofort aktuell nach apply/saveOne)
        $rows = $allIds->map(function (int $pid) use ($participants, $map) {
            $hasEntry = array_key_exists($pid, $map);

            $data = $this->attendanceMap[$pid]
                ?? $this->normalizeRow($map[$pid] ?? null);

            return [
                'id'       => $pid,
                'user'     => $participants[$pid] ?? null,
                'data'     => $data,
                'hasEntry' => $hasEntry,
            ];
        });

        $dir = $this->sortDir === 'desc' ? 'desc' : 'asc';

        $sorted = $rows->sortBy(function ($r) {
            $u = $r['user'];

            // Fallback-Strings (damit nulls nicht alles sprengen)
            $nachname = strtolower((string)($u->nachname ?? ''));
            $vorname  = strtolower((string)($u->vorname ?? ''));
            $email    = strtolower((string)($u->email ?? ''));

            return match ($this->sortBy) {
                'name'      => $nachname . ' ' . $vorname,   // ✅ Name = Nachname + Vorname
                'nachname'  => $nachname,
                'vorname'   => $vorname,
                'email'     => $email,
                'created_at'=> (string)($u->created_at ?? ''),
                default     => $nachname . ' ' . $vorname,
            };
        }, SORT_NATURAL);

        if ($dir === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted->values();
    }

    public function getStatsProperty(): array
    {
        $rows     = $this->rows;
        $total    = $rows->count();

        $marked   = $rows->where('hasEntry', true);
        $unmarked = $rows->where('hasEntry', false)->count();

        $excused  = $marked->where('data.excused', true)->count();
        $late     = $marked->filter(fn ($r) => (int)($r['data']['late_minutes'] ?? 0) > 0)->count();

        $presentMarked = $marked
            ->where('data.present', true)
            ->filter(fn ($r) => ((int)($r['data']['late_minutes'] ?? 0)) === 0)
            ->count();

        $absent = $marked->filter(fn ($r) =>
            empty($r['data']['present']) && empty($r['data']['excused'])
        )->count();

        $present = $presentMarked + $unmarked;

        return [
            'present'  => $present,
            'late'     => $late,
            'excused'  => $excused,
            'absent'   => $absent,
            'unmarked' => $unmarked,
            'total'    => $total,
        ];
    }

    // ---- Helpers ----

    protected function normalizeTime(?string $hhmm): ?string
    {
        if (! $hhmm) return null;
        return preg_match('/^\d{2}:\d{2}$/', $hhmm) ? $hhmm : null;
    }

    protected function toCarbonOnSelectedDate(?string $hhmm): ?Carbon
    {
        if (! $hhmm || ! preg_match('/^\d{2}:\d{2}$/', $hhmm)) return null;

        $base = $this->selectedDay?->date
            ? Carbon::parse($this->selectedDay->date, 'Europe/Berlin')->startOfDay()
            : Carbon::today('Europe/Berlin');

        [$H, $M] = explode(':', $hhmm);
        return $base->copy()->setTime((int) $H, (int) $M);
    }

    protected function plannedTimesForSelectedDay(): array
    {
        $tz   = 'Europe/Berlin';
        $date = $this->selectedDay?->date
            ? Carbon::parse($this->selectedDay->date, $tz)->format('Y-m-d')
            : Carbon::today($tz)->format('Y-m-d');

        $startTime = $this->selectedDay?->start_time;
        $stdRaw    = $this->selectedDay?->std;

        if (! $startTime || $stdRaw === null) {
            $start = Carbon::parse("$date 08:00:00", $tz);
            $end   = Carbon::parse("$date 16:00:00", $tz);
            return [$start, $end];
        }

        $startHms = Carbon::parse($startTime, $tz)->format('H:i:s');
        $start    = Carbon::parse("$date $startHms", $tz);

        $hoursDecimal = (float) $stdRaw;
        $minutes      = (int) round($hoursDecimal * 60);

        $end = $start->copy()->addMinutes($minutes);

        return [$start, $end];
    }

    protected function plannedStartEndStrings(): array
    {
        [$start, $end] = $this->plannedTimesForSelectedDay();
        return [$start?->format('H:i'), $end?->format('H:i')];
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
        $this->updatePrevNextFlags();
        [$plannedStart, $plannedEnd] = $this->plannedStartEndStrings();
    
        $availableDays = $this->course->dates()
            ->orderBy('date')
            ->get();
    
        return view('livewire.tutor.courses.participants-table', [
            'participants'              => $this->participants,
            'rows'                      => $this->rows,
            'stats'                     => $this->stats,
            'selectedDay'               => $this->selectedDay,
            'selectedDayId'             => $this->selectedDayId,
            'selectPreviousDayPossible' => $this->selectPreviousDayPossible,
            'selectNextDayPossible'     => $this->selectNextDayPossible,
            'isLoadingApi'              => $this->isLoadingApi,
            'isDirty'                   => $this->isDirty,
            'plannedStart'              => $plannedStart,
            'plannedEnd'                => $plannedEnd,
            'availableDays'             => $availableDays,
        ]);
    }
}
