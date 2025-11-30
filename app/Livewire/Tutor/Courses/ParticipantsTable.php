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
use Illuminate\Support\Facades\Log;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;
use App\Jobs\ApiUpdates\SyncCourseDayAttendanceJob;

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

    // Abgeleitete Attendance-Map
    public array $attendanceMap = [];

    // UI-Flags für Prev/Next
    public bool $selectPreviousDayPossible = false;
    public bool $selectNextDayPossible     = false;

    public bool $isLoadingApi = false;
    public bool $isDirty      = false;

    // ---- Mount ----
    public function mount(int $courseId, ?int $selectedDayId = null): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::findOrFail($courseId);

        if ($selectedDayId) {
            $this->selectDay($selectedDayId);
        } else {
            $today = now('Europe/Berlin')->toDateString();

            $day = CourseDay::where('course_id', $courseId)
                ->whereDate('date', $today)
                ->first()
                ?: CourseDay::where('course_id', $courseId)->orderBy('date')->first();

            if ($day) {
                $this->selectedDay   = $day;
                $this->selectedDayId = $day->id;
                $this->syncDirtyFlagFromDay($day);
            }
        }

        $this->rebuildAttendanceMap();
        $this->updatePrevNextFlags();
        $this->saveChanges();
    }

protected function syncDirtyFlagFromDay(CourseDay $day): void
{
    $updated = $day->attendance_updated_at;
    $synced  = $day->attendance_last_synced_at;

    $this->isDirty = $updated && (
        !$synced || $synced->lt($updated)
    );

    if ($synced && $updated && $synced->lt($updated->copy()->subMinutes(CourseDay::AUTO_SYNC_THRESHOLD_MINUTES))) {
        $this->isLoadingApi = true;
    }
}

public function checkSyncStatus(): void
{
    if (!$this->selectedDay) {
        $this->isLoadingApi = false;
        return;
    }

    $day = $this->selectedDay->fresh();

    $this->selectedDay = $day;
    $this->rebuildAttendanceMap();
    $this->syncDirtyFlagFromDay($day);

    // wenn updated >= last_synced -> fertig
    $updated = $day->attendance_updated_at;
    $synced  = $day->attendance_last_synced_at;

    if ($synced && $updated && $synced->gte($updated)) {
        $this->isLoadingApi = false;
    }
}


    // ---- Events / Auswahl ----
    /** Auswahl per Kalenderklick (nimmt ID oder { id: ... } entgegen) */
    #[On('calendarEventClick')]
    public function handleCalendarEventClick(...$args): void
    {
        $first = $args[0] ?? null;
        $id    = is_array($first) ? (int) data_get($first, 'id') : (int) $first;
        if ($id > 0) $this->selectDay($id);
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

        $this->syncDirtyFlagFromDay($day);
        $this->isLoadingApi = false;

        $this->rebuildAttendanceMap();
        $this->updatePrevNextFlags();
        $this->resetPage();
    }

    protected function updatePrevNextFlags(): void
    {
        if (!$this->selectedDay) {
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

    // ---- Suche/Sortierung/Pagination ----
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

            // Zeitstempel (Server-perspektive)
            'in'                 => data_get($row, 'timestamps.in'),
            'out'                => data_get($row, 'timestamps.out'),

            // User-Eingaben über Timepicker
            'arrived_at'         => $row['arrived_at'] ?? null, // 'HH:MM'
            'left_at'            => $row['left_at'] ?? null,    // 'HH:MM'
        ];
    }

    protected function rebuildAttendanceMap(): void
    {
        if (!$this->selectedDay) { $this->attendanceMap = []; return; }

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

    /**
     * Zentrale Apply-Methode:
     * - setzt state='dirty' für den Teilnehmer
     * - merkt die Komponente insgesamt als dirty
     * - ruft CourseDay::setAttendance()
     * - aktualisiert die lokale $attendanceMap
     */
    protected function apply(int $participantId, array $patch): void
    {
        $day = $this->dayOrFail();

        // jeden Patch als "dirty" markieren, damit der Sync-Service ihn berücksichtigt
        $patch['state'] = 'dirty';

        // Persistieren im Model
        $day->setAttendance($participantId, $patch);

        // Komponente auf "dirty" stellen
        $this->isDirty = true;

        // Lokales ViewModel mergen
        $existing = $this->attendanceMap[$participantId] ?? [];
        $merged   = array_replace_recursive($this->normalizeRow($existing), $patch);

        // timestamps gesondert mergen
        if (isset($patch['timestamps'])) {
            $merged['in']  = $patch['timestamps']['in']  ?? ($existing['in']  ?? null);
            $merged['out'] = $patch['timestamps']['out'] ?? ($existing['out'] ?? null);
        }

        // arrived_at/left_at ggf. direkt übergeben (für Timepicker)
        if (array_key_exists('arrived_at', $patch)) $merged['arrived_at'] = $patch['arrived_at'];
        if (array_key_exists('left_at',    $patch)) $merged['left_at']    = $patch['left_at'];

        $this->attendanceMap[$participantId] = $this->normalizeRow($merged);

        // Falls serverseitig noch mehr passiert:
        $this->selectedDay?->refresh();
    }

    public function checkInNow(int $participantId): void
    {
        $now   = Carbon::now('Europe/Berlin');
        [$start] = $this->plannedTimesForSelectedDay();

        $late = 0;
        if ($start && $now->gt($start)) {
            $late = $start->diffInMinutes($now);
        }

        $this->apply($participantId, [
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

        $this->apply($participantId, [
            'left_early_minutes' => $leftEarly,
            'timestamps'         => ['out' => $now->toDateTimeString()],
        ]);
    }

    public function markPresent(int $participantId): void
    {
        $this->apply($participantId, [
            'present' => true,
            'excused' => false,
        ]);
    }

    public function markAbsent(int $participantId): void
    {
        $this->apply($participantId, [
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

        // Falls zuvor anwesend -> "früher gegangen" berechnen
        if (!empty($row['present']) && $end && $now->lt($end)) {
            $patch['left_early_minutes'] = $now->diffInMinutes($end);
            $patch['timestamps'] = ['out' => $now->toDateTimeString()];
        }

        $this->apply($participantId, $patch);
    }

    public function setLateMinutes(int $participantId, $minutes): void
    {
        $this->apply($participantId, ['late_minutes' => max(0, (int) $minutes)]);
    }

    public function setLeftEarlyMinutes(int $participantId, $minutes): void
    {
        $this->apply($participantId, ['left_early_minutes' => max(0, (int) $minutes)]);
    }

    public function setArrivalTime(int $participantId, ?string $hhmm): void
    {
        $time = $this->normalizeTime($hhmm); // 'HH:MM' oder null
        $arr  = $this->toCarbonOnSelectedDate($time);

        [$start] = $this->plannedTimesForSelectedDay();
        $late = 0;
        if ($start && $arr && $arr->greaterThan($start)) {
            $late = $start->diffInMinutes($arr);
        }

        $this->apply($participantId, [
            'arrived_at'   => $time,
            'late_minutes' => $late,
            'present'      => true, // wer ankommt, ist anwesend
        ]);
    }

    public function setLeaveTime(int $participantId, ?string $hhmm): void
    {
        $time = $this->normalizeTime($hhmm); // 'HH:MM' oder null
        $out  = $this->toCarbonOnSelectedDate($time);

        [, $end] = $this->plannedTimesForSelectedDay();
        $early = 0;
        if ($end && $out && $out->lessThan($end)) {
            $early = $out->diffInMinutes($end);
        }

        $this->apply($participantId, [
            'left_at'            => $time,
            'left_early_minutes' => $early,
            'present'            => true, // wer geht, war zumindest anwesend
        ]);
    }

    public function setNote(int $participantId, ?string $note): void
    {
        $this->apply($participantId, ['note' => $note]);
    }

    public function bulk(string $action): void
    {
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

    /**
     * Manueller Sync-Button des Dozenten.
     */
    
    public function saveChanges(): void
    {
        $day = $this->dayOrFail();

        $this->isLoadingApi = true;

        try {
            /** @var CourseDayAttendanceSyncService $service */
            $service = app(CourseDayAttendanceSyncService::class);

            $ok = $service->syncToRemote($day);

            // CourseDay neu einlesen (attendance_data, timestamps, etc.)
            $day->refresh();
            $this->selectedDay = $day;

            $this->rebuildAttendanceMap();
            $this->syncDirtyFlagFromDay($day);

            if ($ok) {
                $this->dispatch('notify', type: 'success', message: 'Anwesenheit erfolgreich mit UVS synchronisiert.');
            } else {
                $this->dispatch('notify', type: 'error', message: 'UVS-Sync konnte nicht durchgeführt werden.');
            }
        } catch (\Throwable $e) {
            Log::error('ParticipantsTable.saveChanges: Fehler beim UVS-Sync', [
                'day_id' => $day->id ?? null,
                'error'  => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Fehler beim UVS-Sync. Bitte später erneut versuchen.');
        } finally {
        }
    }

    public function deleteChanges(): void
    {
        $day = $this->dayOrFail();

        $this->isLoadingApi = true;
    // attendance_data als Array holen (oder leeres Array falls null)
    $data = $day->attendance_data ?? [];

    // Teilnehmer-Liste leeren
    $data['participants'] = [];

    // komplettes Array zurück ins Model schreiben
    $day->attendance_data = $data;
    $day->attendance_updated_at = now();
    $day->save();
        try {
            /** @var CourseDayAttendanceSyncService $service */
            $service = app(CourseDayAttendanceSyncService::class);

            $ok = $service->syncToRemote($day);

            // CourseDay neu einlesen (attendance_data, timestamps, etc.)
            $day->refresh();
            $this->selectedDay = $day;

            $this->rebuildAttendanceMap();
            $this->syncDirtyFlagFromDay($day);

            if ($ok) {
                $this->dispatch('notify', type: 'success', message: 'Anwesenheit erfolgreich mit UVS synchronisiert.');
            } else {
                $this->dispatch('notify', type: 'error', message: 'UVS-Sync konnte nicht durchgeführt werden.');
            }
        } catch (\Throwable $e) {
            Log::error('ParticipantsTable.saveChanges: Fehler beim UVS-Sync', [
                'day_id' => $day->id ?? null,
                'error'  => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Fehler beim UVS-Sync. Bitte später erneut versuchen.');
        } finally {
        }
    }

    public function getRowsProperty(): Collection
    {
        $day = $this->selectedDay;
        if (!$day) return collect();

        $att = $day->attendance_data ?? [];
        $map = Arr::get($att, 'participants', []);

        $allIds = collect(array_keys($map))->map(fn ($id) => (int) $id);

        if ($day->course && method_exists($day->course, 'participants')) {
            $rel          = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName();
            $allIds       = $allIds->merge($rel->pluck($qualifiedKey)->all());
        }

        $allIds       = $allIds->map(fn ($id) => (int) $id)->unique()->values();
        $participants = collect();

        if ($day->course && method_exists($day->course, 'participants') && $allIds->isNotEmpty()) {
            $rel          = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName();
            $participants = $rel->whereIn($qualifiedKey, $allIds)->get()->keyBy('id');
        }

        $rows = $allIds->map(function (int $pid) use ($participants, $map) {
            $raw = $map[$pid] ?? null;

            return [
                'id'       => $pid,
                'user'     => $participants[$pid] ?? null,
                'data'     => $this->normalizeRow($raw),
                'hasEntry' => array_key_exists($pid, $map),
            ];
        });

        return $rows
            ->sortBy(fn ($r) => strtolower($r['user']->nachname ?? ('zzzz_'.$r['id'])))
            ->values();
    }

    public function getStatsProperty(): array
    {
        $rows      = $this->rows;
        $total     = $rows->count();

        // Bereits erfasste Datensätze
        $marked    = $rows->where('hasEntry', true);
        $unmarked  = $rows->where('hasEntry', false)->count(); // noch keine Eingabe -> zählt als anwesend

        // --- Basiszählungen ---
        $excused   = $marked->where('data.excused', true)->count();
        $late      = $marked->filter(fn ($r) => (int)($r['data']['late_minutes'] ?? 0) > 0)->count();

        // Normale Anwesenheit = present = true, aber nicht verspätet
        $presentMarked = $marked
            ->where('data.present', true)
            ->filter(fn ($r) => ((int)($r['data']['late_minutes'] ?? 0)) === 0)
            ->count();

        // Fehlend = explizit erfasst, aber weder present noch excused
        $absent = $marked->filter(fn ($r) =>
            empty($r['data']['present']) && empty($r['data']['excused'])
        )->count();

        // Gesamt anwesend = regulär anwesend + unmarked (noch keine Eingabe)
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
        if (!$hhmm) return null;
        return preg_match('/^\d{2}:\d{2}$/', $hhmm) ? $hhmm : null;
    }

    protected function toCarbonOnSelectedDate(?string $hhmm): ?Carbon
    {
        if (!$hhmm || !preg_match('/^\d{2}:\d{2}$/', $hhmm)) return null;

        $base = $this->selectedDay?->date
            ? Carbon::parse($this->selectedDay->date, 'Europe/Berlin')->startOfDay()
            : Carbon::today('Europe/Berlin');

        [$H, $M] = explode(':', $hhmm);
        return $base->copy()->setTime((int) $H, (int) $M);
    }

    /**
     * Start/Ende strikt aus start_time (H:i:s) + std (volle Stunden, ggf. Dezimal) berechnen.
     * Beispiel: start_time=08:00:00, std=9.00  => Ende=17:00:00
     *
     * @return array{0:?Carbon,1:?Carbon}
     */
    protected function plannedTimesForSelectedDay(): array
    {
        $tz   = 'Europe/Berlin';
        $date = $this->selectedDay?->date
            ? Carbon::parse($this->selectedDay->date, $tz)->format('Y-m-d')
            : Carbon::today($tz)->format('Y-m-d');

        $startTime = $this->selectedDay?->start_time; // Carbon|string|null
        $stdRaw    = $this->selectedDay?->std;        // z. B. 9.00 (als string/float/int)

        if (!$startTime || $stdRaw === null) {
            // Fallback: 08–16
            $start = Carbon::parse("$date 08:00:00", $tz);
            $end   = Carbon::parse("$date 16:00:00", $tz);
            return [$start, $end];
        }

        // Start auf gewählten Tag legen
        $startHms = Carbon::parse($startTime, $tz)->format('H:i:s');
        $start    = Carbon::parse("$date $startHms", $tz);

        // std als Dezimalstunden in Minuten
        $hoursDecimal = (float) $stdRaw;             // 9.00 -> 9.0
        $minutes      = (int) round($hoursDecimal * 60);

        $end = $start->copy()->addMinutes($minutes);

        return [$start, $end];
    }

    /** Praktische Strings 'H:i' für Blade-Timepicker-Defaults */
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

    // ---- Render ----
    public function render()
    {
        $this->updatePrevNextFlags();
        [$plannedStart, $plannedEnd] = $this->plannedStartEndStrings();

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
            // Neu: Defaults für Timepicker
            'plannedStart'              => $plannedStart, // 'H:i'
            'plannedEnd'                => $plannedEnd,   // 'H:i'
        ]);
    }
}
