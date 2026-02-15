<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class CourseShowDoku extends Component
{
    /** Optional via Blade: <livewire:... :course-id="123" /> */
    public $courseId;

    /** Für das Akkordeon: genau ein Tag offen */
    public ?int $openDayId = null;
    public ?Course $course = null;
    public ?int $classRepresentativeUserId = null;

    public function mount($courseId = null)
    {
        // Versuche Prop, sonst aus Route (z.B. .../course/{klassenId})
        $this->courseId = $courseId
            ?? request()->route('klassenId')
            ?? request()->route('courseId')
            ?? request()->route('id');

        // Fallback: erstes passendes Course-Record suchen (klassen_id oder id)
        $course = Course::where('klassen_id', $this->courseId)->first()
            ?? Course::find($this->courseId);

        abort_if(!$course, 404, 'Kurs nicht gefunden.');
        $this->courseId = $course->id;
        $this->course = $course;
        $this->ensureClassRepresentative();

        // Standard: der nächste (oder erste) Kurstag ist offen
        $next = CourseDay::where('course_id', $this->courseId)
            ->orderBy('date')->get();
        $this->openDayId = optional($next->firstWhere('date', '>=', now()))?->id
            ?? optional($next->first())?->id;
    }

    public function toggleDay($dayId)
    {
        $this->openDayId = ($this->openDayId === (int)$dayId) ? null : (int)$dayId;
    }

public function getDaysCountProperty()
{
    return $this->days->count();
}

public function getCurrentUserIdProperty(): ?int
{
    return Auth::id();
}

public function getIsClassRepresentativeProperty(): bool
{
    return (int) $this->classRepresentativeUserId > 0
        && (int) $this->currentUserId === (int) $this->classRepresentativeUserId;
}

public function getDokuAcknowledgedProperty(): bool
{
    return (bool) $this->course?->files()
        ->where('type', 'sign_course_doku_participant')
        ->exists();
}

public function getAllCourseDaysCompletedProperty(): bool
{
    if (! $this->course) {
        return false;
    }

    $total = CourseDay::query()
        ->where('course_id', $this->course->id)
        ->count();

    if ($total === 0) {
        return false;
    }

    $completed = CourseDay::query()
        ->where('course_id', $this->course->id)
        ->where('note_status', CourseDay::NOTE_STATUS_COMPLETED)
        ->count();

    return $completed === $total;
}

public function startCourseDokuAcknowledgement(): void
{
    if (! $this->course) {
        $this->dispatch('toast', type: 'error', message: 'Kurs nicht gefunden.');
        return;
    }

    if (! $this->allCourseDaysCompleted) {
        $this->dispatch('toast', type: 'error', message: 'Bestätigung erst möglich, wenn alle Kurstage auf fertig stehen.');
        return;
    }

    if (! $this->isClassRepresentative) {
        $this->dispatch('toast', type: 'error', message: 'Nur der Klassensprecher kann die Kurs-Dokumentation bestätigen.');
        return;
    }

    if ($this->dokuAcknowledged) {
        $this->dispatch('toast', type: 'info', message: 'Die Kurs-Dokumentation wurde bereits bestätigt.');
        return;
    }

    $this->dispatch('openSignatureForm', [
        'fileableType' => Course::class,
        'fileableId'   => $this->course->id,
        'fileType'     => 'sign_course_doku_participant',
        'label'        => 'Teilnehmer-Bestätigung Kurs-Dokumentation',
        'confirmText'  => 'Ich bestätige als Klassensprecher, dass ich die Kurs-Dokumentation zur Kenntnis genommen und als vollständig anerkannt habe.',
    ]);
}

#[On('signatureCompleted')]
public function handleSignatureCompleted(array $payload): void
{
    if (
        data_get($payload, 'fileableType') !== Course::class
        || (int) data_get($payload, 'fileableId') !== (int) $this->courseId
        || data_get($payload, 'fileType') !== 'sign_course_doku_participant'
    ) {
        return;
    }

    if (! $this->course) {
        return;
    }

    $this->course->setSetting('course_doku_acknowledged_at', now()->toIso8601String());
    $this->course->setSetting('course_doku_acknowledged_person_id', Auth::user()?->person?->id);
    $this->course->setSetting('course_doku_acknowledged_user_id', Auth::id());
    $this->course->save();

    $this->dispatch('toast', type: 'success', message: 'Kurs-Dokumentation wurde bestätigt.');
}

protected function ensureClassRepresentative(): void
{
    if (! $this->course) {
        return;
    }

    $currentRepresentative = (int) $this->course->getSetting('class_representative_user_id', 0);
    $currentUserId = (int) (Auth::id() ?? 0);
    $assignedAtRaw = $this->course->getSetting('class_representative_assigned_at');
    $assignedAt = $assignedAtRaw ? \Illuminate\Support\Carbon::parse($assignedAtRaw) : null;
    $assignmentExpired = $assignedAt ? $assignedAt->lte(now()->subMinutes(5)) : false;
    $hasDokuSignature = (bool) $this->course->files()
        ->where('type', 'sign_course_doku_participant')
        ->exists();

    // Regel: Der erste User, der die Doku-Ansicht aufruft, wird Klassensprecher.
    if ($currentRepresentative <= 0 && $currentUserId > 0) {
        $this->course->setSetting('class_representative_user_id', $currentUserId);
        $this->course->setSetting('class_representative_assigned_at', now()->toIso8601String());
        $this->course->setSetting('class_representative_assigned_by_first_open', true);
        $this->course->save();
        $this->classRepresentativeUserId = $currentUserId;
        return;
    }

    // Wenn nach 5 Minuten noch nicht unterschrieben wurde, darf der nächste Aufrufer übernehmen.
    if (
        $currentUserId > 0
        && $currentRepresentative > 0
        && $currentRepresentative !== $currentUserId
        && ! $hasDokuSignature
        && $assignmentExpired
    ) {
        $this->course->setSetting('class_representative_user_id', $currentUserId);
        $this->course->setSetting('class_representative_assigned_at', now()->toIso8601String());
        $this->course->setSetting('class_representative_reassigned_after_timeout', true);
        $this->course->save();
        $this->classRepresentativeUserId = $currentUserId;
        return;
    }

    $this->classRepresentativeUserId = $currentRepresentative > 0 ? $currentRepresentative : null;
}

public function getDateRangeProperty()
{
    if ($this->days->isEmpty()) {
        return ['from' => null, 'to' => null];
    }
    $from = \Illuminate\Support\Carbon::parse($this->days->first()->date)->startOfDay();
    $to   = \Illuminate\Support\Carbon::parse($this->days->last()->date)->startOfDay();
    return ['from' => $from, 'to' => $to];
}

/** Gesamte Doku als TXT streamen (on-the-fly) */
public function exportAllDoku()
{
    $lines = [];
    $lines[] = 'Kurs-Dokumentation (alle Tage)';
    $lines[] = str_repeat('=', 32);

    foreach ($this->days as $day) {
        $date = $day->date ? \Illuminate\Support\Carbon::parse($day->date)->format('d.m.Y') : '—';
        $topic = $day->topic ?: '—';

        // HTML aus notes zu Text runterbrechen (falls HTML enthalten)
        $notes = strip_tags((string)$day->notes);
        $notes = preg_replace("/\r\n|\r|\n/", PHP_EOL, $notes);

        $lines[] = '';
        $lines[] = "Datum: {$date}";
        $lines[] = "Thema: {$topic}";
        $lines[] = str_repeat('-', 24);
        $lines[] = $notes !== '' ? $notes : '(keine Notizen)';
    }

    $filename = 'kurs-doku-' . now()->format('Ymd-His') . '.txt';
    $content = implode(PHP_EOL, $lines);

    return response()->streamDownload(function () use ($content) {
        echo $content;
    }, $filename, ['Content-Type' => 'text/plain; charset=utf-8']);
}


    public function getDaysProperty()
    {
        return CourseDay::where('course_id', $this->courseId)
            ->orderBy('date')->orderBy('start_time')
            ->get();
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
        return view('livewire.user.program.course.course-show-doku', [
            'days' => $this->days,
        ]);
    }
}
