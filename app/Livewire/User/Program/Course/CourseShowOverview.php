<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\Person;
use App\Models\CourseParticipantEnrollment;
use Illuminate\Support\Collection;

class CourseShowOverview extends Component
{
    public string $klassenId;

    public array $course = [];
    public array $stats = [];

    public ?Person $tutor = null;
    public int $participantsCount = 0;

    public ?float $participantScore = null; // Dein Ergebnis (Ø tn_punkte > 0)
    public ?float $classAverage     = null; // Klassenschnitt (Ø klassenschnitt > 0)

    protected function safeAvg(Collection $c, string $key): ?float
    {
        // Ø nur über >0 Werte; wenn nichts vorliegt -> null
        $vals = $c->pluck($key)
            ->filter(fn ($v) => is_numeric($v) && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->values();

        return $vals->isNotEmpty() ? $vals->avg() : null;
    }

    public function mount(string $klassenId): void
    {
        $this->klassenId = $klassenId;

        $person = Auth::user()?->person;
        if (! $person) abort(404);

        // Kurs + benötigte Relationen laden
        $course = Course::query()
            ->with(['tutor', 'days'])
            ->where('klassen_id', $klassenId)
            ->firstOrFail();

        // Einschreibung prüfen
        $enrolled = CourseParticipantEnrollment::query()
            ->where('course_id', $course->id)
            ->where('person_id', $person->id)
            ->exists();

        if (! $enrolled) abort(404, 'Nicht eingeschrieben.');

        // ViewModel
        $start = $course->planned_start_date ? Carbon::parse($course->planned_start_date) : null;
        $end   = $course->planned_end_date   ? Carbon::parse($course->planned_end_date)   : null;

        $this->course = [
            'id'           => $course->id,
            'klassen_id'   => $course->klassen_id,
            'title'        => $course->title,
            'description'  => $course->description,
            'room'         => $course->room,
            'start'        => $start?->toDateString(),
            'end'          => $end?->toDateString(),
            'zeitraum_fmt' => ($start && $end)
                ? $start->locale('de')->isoFormat('ll') . ' – ' . $end->locale('de')->isoFormat('ll')
                : '—',
            'status'       => $this->status($start, $end),
            'tutor'        => $course->tutor
                ? trim(($course->tutor->vorname ?? '') . ' ' . ($course->tutor->nachname ?? ''))
                : null,
        ];

        $this->tutor = $course->tutor;
        $this->participantsCount = (int) ($course->participantsCount ?? 0);

        $this->stats = [
            'tage'      => $course->days->count(),
            'einheiten' => (int) $course->days->sum('units'),
            'start'     => $this->course['start'] ?? null,
            'end'       => $this->course['end'] ?? null,
        ];

        $pd = $person->programdata ?? [];
        $blocks = collect($pd['tn_baust'] ?? [])
            ->where('klassen_id', $klassenId)
            ->values();

        // Ø über >0 Punkte
        $this->participantScore = $this->safeAvg($blocks, 'tn_punkte');
        $this->classAverage     = $this->safeAvg($blocks, 'klassenschnitt');
    }

    private function status(?Carbon $start, ?Carbon $end): string
    {
        $now = Carbon::now('Europe/Berlin');

        if ($start && $now->lt($start)) return 'Geplant';
        if ($start && $end && $now->between($start, $end)) return 'Laufend';
        if ($end && $now->gt($end)) return 'Abgeschlossen';

        return 'Offen';
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
        return view('livewire.user.program.course.course-show-overview');
    }
}
