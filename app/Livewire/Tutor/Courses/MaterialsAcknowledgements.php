<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseMaterialAcknowledgement; // ← an dein Modell angepasst
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Illuminate\Support\Collection;

class MaterialsAcknowledgements extends Component
{
    public int $courseId;
    public Course $course;

    public bool $openModal = false;
    public string $search = '';

    public int $total = 0;
    public int $ackCount = 0;
    public int $pendingCount = 0;

    public Collection $rows;

    protected $listeners = ['materials-ack-refresh' => '$refresh'];

    public function mount(int $courseId): void
    {
        $this->courseId = $courseId;
        $this->course   = Course::query()->findOrFail($courseId);

        // Hol einfach die Personen aus der Relation, ohne users.* Selekt:
        $participants = $this->course->participants()->get();

        // Acks (per person_id) vorab laden und keyBy
        $acks = CourseMaterialAcknowledgement::query()
            ->where('course_id', $this->courseId)
            ->get()
            ->keyBy('person_id');

        // Helper, um einen robusten Anzeigenamen zu bauen
        $nameFor = function ($p): string {
            // Versuche gängige Felder in deiner Person-Struktur
            $name = $p->name
                ?? trim(($p->nachname ?? '').' '.($p->vorname ?? ''))
                ?? ($p->fullname ?? null)
                ?? ($p->anzeige_name ?? null);

            return trim($name ?: '—');
        };

        $emailFor = function ($p): ?string {
            return $p->email
                ?? $p->email_priv ?? $p->email_private
                ?? $p->kontakt_email ?? null;
        };

$this->rows = collect($participants)
    ->map(function ($p) use ($acks, $nameFor, $emailFor) {
        $ack = $acks->get($p->id);

        return (object) [
            'id'              => $p->id,            // für einfache Nutzung
            'person_id'       => $p->id,
            'person'          => $p,                // 👈 vollständiges Person-Model
            'name'            => $nameFor($p),
            'email'           => $emailFor($p),
            'acknowledged'    => (bool) ($ack?->acknowledged_at),
            'acknowledged_at' => $ack?->acknowledged_at
                ? \Illuminate\Support\Carbon::parse($ack->acknowledged_at)
                : null,
        ];
    })
    ->sortBy(fn($r) => \Illuminate\Support\Str::lower($r->name))
    ->values();



        $this->total        = $this->rows->count();
        $this->ackCount     = $this->rows->where('acknowledged', true)->count();
        $this->pendingCount = $this->total - $this->ackCount;
    }

    public function getFilteredRowsProperty(): Collection
    {
        $q = trim($this->search);
        if ($q === '') return $this->rows;

        $qLower = mb_strtolower($q);
        return $this->rows->filter(function ($r) use ($qLower) {
            return str_contains(mb_strtolower($r->name), $qLower)
                || str_contains(mb_strtolower($r->email ?? ''), $qLower);
        });
    }

    public function open(): void { $this->openModal = true; }
    public function close(): void { $this->openModal = false; }

    public function render()
    {
        return view('livewire.tutor.courses.materials-acknowledgements', [
            'filteredRows' => $this->filteredRows,
        ]);
    }
}
