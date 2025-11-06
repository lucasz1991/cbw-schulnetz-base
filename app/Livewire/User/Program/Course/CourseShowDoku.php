<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use App\Models\Course;
use App\Models\CourseDay;
use Illuminate\Support\Str;

class CourseShowDoku extends Component
{
    /** Optional via Blade: <livewire:... :course-id="123" /> */
    public $courseId;

    /** Für das Akkordeon: genau ein Tag offen */
    public ?int $openDayId = null;

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
