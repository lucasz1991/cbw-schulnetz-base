<?php

namespace App\Livewire\User\Program\Course;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseParticipantEnrollment;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentParticipantCourseScope;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CourseShow extends Component
{
    private const COURSE_WAIT_TIMEOUT_MS = 10_000;

    private const COURSE_WAIT_START_QUERY = 'loading_started_at';

    public string $klassenId;

    /** Kurs + Days als Arrays fürs Blade */
    public array $courseArray = [];

    public ?Course $course = null;

    public array $days = [];

    public bool $courseLoading = false;

    public bool $courseUnavailable = false;

    public int $courseLoadingStartedAtMs = 0;

    public string $pendingCourseTitle = 'Baustein';

    /** Navigation innerhalb der eingeschriebenen Kurse der Person */
    public ?array $prev = null;

    public ?array $next = null;

    public int $index = -1;

    public int $total = 0;

    public EloquentCollection $enrolledCourses;

    public function mount(string $klassenId): void
    {
        $this->klassenId = $klassenId;
        $this->courseLoadingStartedAtMs = $this->normalizeLoadingStartedAtMilliseconds(
            request()->query(self::COURSE_WAIT_START_QUERY)
        );

        $user = Auth::user();
        $person = $this->resolveCurrentPerson($user);

        if (! $person) {
            abort(404);
        }

        if ($this->loadCourse($person)) {
            return;
        }

        // Nur Bausteine aus dem aktuellen Programmdaten-Kontext duerfen auf
        // den asynchron erzeugten lokalen Kurs-/Enrollment-Datensatz warten.
        // Unbekannte oder fremde IDs bleiben eine echte 404.
        if (! $this->isExpectedProgramCourse($person)) {
            abort(404);
        }

        $this->pendingCourseTitle = $this->pendingCourseTitleFor($person);

        if ($this->courseWaitTimedOut()) {
            $this->courseUnavailable = true;

            return;
        }

        $this->courseLoading = true;
    }

    public function pollCourse(): void
    {
        if (! $this->courseLoading) {
            return;
        }

        $person = $this->resolveCurrentPerson(Auth::user());
        if (! $person || ! $this->isExpectedProgramCourse($person)) {
            abort(404);
        }

        if ($this->loadCourse($person)) {
            return;
        }

        if ($this->courseWaitTimedOut()) {
            $this->courseLoading = false;
            $this->courseUnavailable = true;
        }
    }

    public function retryCourseLoad(): void
    {
        $person = $this->resolveCurrentPerson(Auth::user());
        if (! $person || ! $this->isExpectedProgramCourse($person)) {
            abort(404);
        }

        $this->courseLoadingStartedAtMs = (int) floor(microtime(true) * 1000);
        $this->courseUnavailable = false;

        if ($this->loadCourse($person)) {
            return;
        }

        $this->courseLoading = true;
    }

    protected function loadCourse(Person $person): bool
    {
        $course = Course::query()
            ->where('klassen_id', $this->klassenId)
            ->first();

        if (! $course) {
            return false;
        }

        $enrolled = CourseParticipantEnrollment::query()
            ->where('course_id', $course->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where(function ($query) use ($person) {
                CurrentParticipantCourseScope::applyForPerson($query, $person, 'course_participant_enrollments', null);
            })
            ->exists();

        if (! $enrolled) {
            return false;
        }

        $this->course = $course;
        $this->courseArray = $this->mapCourse($course);
        $this->days = $course->days->map(fn ($day) => $this->mapDay($day))->all();

        $enrolledCourses = Course::query()
            ->join('course_participant_enrollments as cpe', 'cpe.course_id', '=', 'courses.id')
            ->whereNull('cpe.deleted_at')
            ->where('cpe.is_active', true)
            ->where(function ($query) use ($person) {
                CurrentParticipantCourseScope::applyForPerson($query, $person, 'cpe', 'courses');
            })
            ->orderBy('courses.planned_start_date')
            ->get([
                'courses.id as course_id',              // eindeutiger Alias
                'courses.klassen_id',
                'courses.title',
                'courses.planned_start_date',
                'courses.planned_end_date',
            ])
            ->map(fn ($c) => [
                'klassen_id' => $c->klassen_id,
                'title' => $c->title,
                'start' => $c->planned_start_date,
                'end' => $c->planned_end_date,
                // 'course_id' => $c->course_id, // optional, falls du’s brauchst
            ])
            ->values();

        $this->total = $enrolledCourses->count();
        $this->index = $enrolledCourses->search(fn ($c) => $c['klassen_id'] === $this->klassenId);

        $this->prev = ($this->index > 0) ? $enrolledCourses[$this->index - 1] : null;
        $this->next = ($this->index !== false && $this->index + 1 < $this->total) ? $enrolledCourses[$this->index + 1] : null;

        $this->courseLoading = false;
        $this->courseUnavailable = false;

        return true;
    }

    protected function isExpectedProgramCourse(Person $person): bool
    {
        $identifiers = CurrentParticipantCourseScope::identifiersFor($person);

        return in_array(trim($this->klassenId), $identifiers['klassen_ids'], true);
    }

    protected function pendingCourseTitleFor(Person $person): string
    {
        $block = collect(data_get($person->programdata, 'tn_baust', []))
            ->first(fn ($candidate) => is_array($candidate)
                && trim((string) ($candidate['klassen_id'] ?? '')) === trim($this->klassenId));

        if (! is_array($block)) {
            return 'Baustein';
        }

        return trim((string) ($block['langbez'] ?? $block['kurzbez'] ?? '')) ?: 'Baustein';
    }

    protected function normalizeLoadingStartedAtMilliseconds(mixed $value, ?int $nowMs = null): int
    {
        $nowMs ??= (int) floor(microtime(true) * 1000);
        $candidate = is_numeric($value) ? (int) $value : 0;

        // Nur plausible Klickzeitpunkte uebernehmen. Zukuenftige oder sehr
        // alte Query-Werte duerfen das Zeitlimit nicht verlaengern.
        if ($candidate <= 0 || $candidate > $nowMs || $candidate < $nowMs - 60_000) {
            return $nowMs;
        }

        return $candidate;
    }

    protected function courseWaitTimedOut(?int $nowMs = null): bool
    {
        $nowMs ??= (int) floor(microtime(true) * 1000);

        return $nowMs - $this->courseLoadingStartedAtMs >= self::COURSE_WAIT_TIMEOUT_MS;
    }

    protected function resolveCurrentPerson(?User $user): ?Person
    {
        if ($user && str_starts_with($this->klassenId, 'ec-')) return \App\Services\Coaching\Access::participantForCourse($user, null, $this->klassenId);
        if ($user && method_exists($user, 'resolvePortalDrivingPerson')) {
            return $user->resolvePortalDrivingPerson() ?? $user->person;
        }

        return $user?->person;
    }

    private function mapCourse(Course $c): array
    {
        $start = $c->planned_start_date ? Carbon::parse($c->planned_start_date) : null;
        $end = $c->planned_end_date ? Carbon::parse($c->planned_end_date) : null;

        return [
            'id' => $c->id,
            'klassen_id' => $c->klassen_id,
            'title' => $c->title,
            'description' => $c->description,
            'room' => $c->room,
            'start' => $start?->toDateString(),
            'end' => $end?->toDateString(),
            'zeitraum_fmt' => ($start && $end)
                                ? $start->locale('de')->isoFormat('ll').' – '.$end->locale('de')->isoFormat('ll')
                                : '—',
            'status' => $this->deriveCourseStatus($start, $end),
            'tutor' => optional($c->primaryTutorPerson)->only(['vorname', 'nachname']),
        ];
    }

    private function mapDay(CourseDay $d): array
    {
        return [
            'id' => $d->id,
            'date' => $d->date ? Carbon::parse($d->date)->locale('de')->isoFormat('DD.MM.YYYY') : '—',
            'units' => $d->units ?? null,
            'topic' => $d->topic ?? null,
            'notes' => $d->notes ?? null,
        ];
    }

    private function deriveCourseStatus(?Carbon $start, ?Carbon $end): string
    {
        $now = Carbon::now('Europe/Berlin');
        if ($start && $now->lt($start)) {
            return 'geplant';
        }
        if ($start && $end && $now->between($start, $end)) {
            return 'aktiv';
        }
        if ($end && $now->gt($end)) {
            return 'abgeschlossen';
        }

        return 'offen';
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show', [
            'course' => $this->course,
            'courseArray' => $this->courseArray,
            'days' => $this->days,
            'prev' => $this->prev,
            'next' => $this->next,
            'index' => $this->index,
            'total' => $this->total,
        ])->layout('layouts.app');
    }
}
