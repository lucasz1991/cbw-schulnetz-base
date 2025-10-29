<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Course;
use App\Models\Person;
use App\Models\CourseParticipantEnrollment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection; // <- neu
use Illuminate\Support\Collection;

class CourseShowOverview extends Component
{
    public string $klassenId;

    public array $course = [];
    public array $stats = [];
    public ?array $prev = null;
    public ?array $next = null;
    public int $index = -1;
    public int $total = 0;

    public ?Person $tutor = null;
    public int $participantsCount = 0;

    public EloquentCollection $enrolledCourses; // <- als Eloquent-Collection typisieren

    public ?float $participantScore = null; // Dein Ergebnis (Ø tn_punkte > 0)
    public ?float $classAverage     = null; // Klassenschnitt (Ø klassenschnitt > 0)

    protected function safeAvg(Collection $c, string $key): ?float
    {
        // Ø nur über >0 Werte; wenn nichts vorliegt -> null
        $vals = $c->pluck($key)
                  ->filter(fn($v) => is_numeric($v) && (float)$v > 0)
                  ->map(fn($v) => (float)$v)
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
            ->with(['tutor', 'days'])   // <- eager load
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
            'id'          => $course->id,
            'klassen_id'  => $course->klassen_id,
            'title'       => $course->title,
            'description' => $course->description,
            'room'        => $course->room,
            'start'       => $start?->toDateString(),
            'end'         => $end?->toDateString(),
            'zeitraum_fmt'=> ($start && $end) ? $start->locale('de')->isoFormat('ll').' – '.$end->locale('de')->isoFormat('ll') : '—',
            'status'      => $this->status($start, $end),
            'tutor'       => $course->tutor ? trim(($course->tutor->vorname ?? '').' '.($course->tutor->nachname ?? '')) : null,
        ];

        $this->tutor = $course->tutor;                    // <- echtes Person-Model
        $this->participantsCount = (int) ($course->participantsCount ?? 0);

        $this->stats = [
            'tage'      => $course->days->count(),
            'einheiten' => (int) $course->days->sum('units'),
            'start'     => $this->course['start'] ?? null,
            'end'       => $this->course['end'] ?? null,
        ];

        // EINGESCHRIEBENE KURSE ALS MODELLE LADEN
        $this->enrolledCourses = Course::query()
            ->select('courses.*')                  // <- wichtig bei join: Course-Model bleibt intakt
            ->with(['days'])                       // <- Relationen für Frontend
            ->withCount('days')                    // <- z.B. $rc->days_count
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->where('cpe.person_id', $person->id)
            ->orderBy('courses.planned_start_date')
            ->get();

        $this->total = $this->enrolledCourses->count();
        $this->index = (int) $this->enrolledCourses->search(fn(Course $c) => $c->klassen_id === $this->klassenId);

        $this->prev  = ($this->index > 0)
            ? [
                'klassen_id' => $this->enrolledCourses[$this->index - 1]->klassen_id,
                'title'      => $this->enrolledCourses[$this->index - 1]->title,
              ]
            : null;

        $this->next  = ($this->index !== false && $this->index + 1 < $this->total)
            ? [
                'klassen_id' => $this->enrolledCourses[$this->index + 1]->klassen_id,
                'title'      => $this->enrolledCourses[$this->index + 1]->title,
              ]
            : null;
        
        $pd = $person->programdata ?? [];
        $blocks = collect($pd['tn_baust'] ?? [])
            ->where('klassen_id', $klassenId)
            ->values();

        // Ø über >0 Punkte
        $this->participantScore = $this->safeAvg($blocks, 'tn_punkte');       // z.B. 91
        $this->classAverage     = $this->safeAvg($blocks, 'klassenschnitt');  // z.B. 71
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
            <div role="status" class=" animate-pulse">
                <section class="relative min-h-[50vh]">
                    <div class="container mx-auto px-5 my-4  space-y-6">
                        <div class="grid grid-cols-1">
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show-overview');
    }
}
