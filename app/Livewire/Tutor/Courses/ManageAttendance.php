<?php

namespace App\Livewire\Tutor\Courses;

use Livewire\Component;
use App\Models\CourseDay;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class ManageAttendance extends Component
{
    public ?int $selectedDayId = null;
    public ?CourseDay $selectedDay = null;

    public bool $showManageAttendanceModal = false;

    protected $listeners = [
        'daySelected' => 'mount',
    ];

    public function mount(?int $selectedDayId = null): void
    {
        if ($selectedDayId) {
            $this->selectedDayId = $selectedDayId;
            $this->selectedDay   = $this->day;
        }
    }

    public function getDayProperty(): ?CourseDay
    {
        return $this->selectedDayId ? CourseDay::find($this->selectedDayId) : null;
    }

    /** Vereinheitlichte Row für UI (tagesweit, inkl. in/out). */
    protected function normalize(?array $row): array
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

    /** Alle Teilnehmer (tagesweite Struktur) in eine Collection mappen. */
    public function getRowsProperty(): Collection
    {
        $day = $this->day;
        if (!$day) return collect();

        $att = $day->attendance_data ?? [];
        $map = Arr::get($att, 'participants', []);

        // IDs aus JSON + Relation zusammenführen
        $allIds = collect(array_keys($map))->map(fn ($id) => (int)$id);

        if ($day->course && method_exists($day->course, 'participants')) {
            $rel = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName(); // "users.id"
            $allIds = $allIds->merge($rel->pluck($qualifiedKey)->all());
        }

        $allIds = $allIds->map(fn ($id) => (int)$id)->unique()->values();

        // Teilnehmer laden (qualifizierter whereIn)
        $participants = collect();
        if ($day->course && method_exists($day->course, 'participants') && $allIds->isNotEmpty()) {
            $rel = $day->course->participants();
            $qualifiedKey = $rel->getModel()->getQualifiedKeyName(); // "users.id"
            $participants = $rel->whereIn($qualifiedKey, $allIds)->get()->keyBy('id');
        }

        $rows = $allIds->map(function (int $pid) use ($participants, $map) {
            return [
                'id'   => $pid,
                'user' => $participants[$pid] ?? null,
                'data' => $this->normalize($map[$pid] ?? null),
            ];
        });

        return $rows
            ->sortBy(fn ($r) => strtolower($r['user']->name ?? ('zzzz_'.$r['id'])))
            ->values();
    }

    /** Tages-Statistik (eine Zeile pro TN) */
    public function getStatsProperty(): array
    {
        $rows    = $this->rows;
        $present = $rows->where('data.present', true)->count();
        $excused = $rows->where('data.excused', true)->count();
        $late    = $rows->filter(fn ($r) => ($r['data']['late_minutes'] ?? 0) > 0)->count();
        $total   = max($rows->count(), 0);
        $absent  = $total - $present - $excused;

        return compact('present', 'excused', 'late', 'absent', 'total');
    }

    /** Status (mit start/end) direkt fürs View bereitstellen */
    public function getStatusProperty(): array
    {
        $att = $this->day?->attendance_data ?? [];
        $status = Arr::get($att, 'status', []);
        return array_merge([
            'start'      => 0,
            'end'        => 0,
            'created_at' => null,
            'updated_at' => null,
        ], $status ?? []);
    }

    // ---------------------------
    // Actions (alle nutzen setAttendance im Model)
    // ---------------------------

    protected function dayOrFail(): CourseDay
    {
        $day = $this->day;
        abort_if(!$day, 404, 'Day not found');
        return $day;
    }

    protected function currentRow(int $participantId): ?array
    {
        return Arr::get($this->day?->attendance_data, "participants.$participantId");
    }

    protected function apply(int $participantId, array $patch): void
    {
        $day = $this->dayOrFail();
        $day->setAttendance($participantId, $patch);
        $this->selectedDay?->refresh();
    }

    /** ✔️ Check-in (setzt present, berechnet Verspätung ggü. Slot 1) */
    public function checkInNow(int $participantId): void
    {
        $now   = Carbon::now();
        $date  = $this->day?->date?->format('Y-m-d');
        $start = data_get($this->day?->day_sessions, '1.start', '08:00'); // fallback

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

    /** ❌ Abwesend (wenn zuvor anwesend -> Check-out + früh-weg berechnen) */
    public function markAbsentNow(int $participantId): void
    {
        $row  = $this->currentRow($participantId) ?? [];
        $now  = Carbon::now();
        $date = $this->day?->date?->format('Y-m-d');

        // Ende: nimm Block 4, sonst fallback auf day->end_time oder 16:00
        $endStr = data_get($this->day?->day_sessions, '4.end')
              ?? $this->day?->end_time?->format('H:i')
              ?? '16:00';

        $patch = [
            'present' => false,
            'excused' => false,
        ];

        // Wenn er aktuell als anwesend gilt -> checkout + left_early berechnen
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

    /** Minuten/Notiz setzen (Popover/Inputs) */
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

    /** Für Bulk „Checkout alle“ weiterhin verfügbar */
    public function checkOutNow(int $participantId): void
    {
        $now  = Carbon::now();
        $date = $this->day?->date?->format('Y-m-d');

        $endStr = data_get($this->day?->day_sessions, '4.end')
              ?? $this->day?->end_time?->format('H:i')
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

    /** Bulk-Operationen */
    public function bulk(string $action): void
    {
        $ids = $this->rows->pluck('id');
        foreach ($ids as $pid) {
            match ($action) {
                'all_present'  => $this->apply($pid, ['present' => true, 'excused' => false]),
                'all_excused'  => $this->apply($pid, ['excused' => true, 'present' => false]),
                'all_absent'   => $this->apply($pid, ['present' => false, 'excused' => false]),
                'checkin_all'  => $this->checkInNow($pid),
                'checkout_all' => $this->checkOutNow($pid),
                default => null,
            };
        }
    }

    // Tagesstatus (Vormittag/Nachmittag)
    public function markStartDone(): void
    {
        $day  = $this->dayOrFail();
        $data = $day->attendance_data ?? [];
        $now  = now()->toDateTimeString();

        $count = $this->rows->filter(fn($r) => $r['data']['present'] || $r['data']['excused'])->count();
        $data['status'] = array_merge([
            'start' => 0,'end' => 0,'created_at' => null,'updated_at' => null,
        ], $data['status'] ?? []);
        $data['status']['start'] = $count ?: 1; // min. 1 = „in Bearbeitung“
        $data['status']['updated_at'] = $now;
        if (!$data['status']['created_at']) $data['status']['created_at'] = $now;

        $day->attendance_data = $data;
        $day->save();
        $this->selectedDay?->refresh();
    }

    public function undoStartDone(): void
    {
        $day  = $this->dayOrFail();
        $data = $day->attendance_data ?? [];
        $data['status']['start'] = 0;
        $data['status']['updated_at'] = now()->toDateTimeString();
        $day->attendance_data = $data;
        $day->save();
        $this->selectedDay?->refresh();
    }

    public function markEndDone(): void
    {
        $day  = $this->dayOrFail();
        $data = $day->attendance_data ?? [];
        $data['status'] = array_merge(['start'=>0,'end'=>0,'created_at'=>null,'updated_at'=>null], $data['status'] ?? []);
        $data['status']['end'] = $this->rows->filter(fn($r) => $r['data']['present'] || $r['data']['excused'])->count() ?: 1;
        $data['status']['updated_at'] = now()->toDateTimeString();
        $day->attendance_data = $data;
        $day->save();
        $this->selectedDay?->refresh();
    }

    public function undoEndDone(): void
    {
        $day  = $this->dayOrFail();
        $data = $day->attendance_data ?? [];
        $data['status']['end'] = 0;
        $data['status']['updated_at'] = now()->toDateTimeString();
        $day->attendance_data = $data;
        $day->save();
        $this->selectedDay?->refresh();
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
        return view('livewire.tutor.courses.manage-attendance', [
            'day'    => $this->day,
            'rows'   => $this->rows,
            'stats'  => $this->stats,
            'status' => $this->status,
        ]);
    }
}
