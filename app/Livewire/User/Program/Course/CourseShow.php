<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class CourseShow extends Component
{
    public string $courseId;         // von der Route
    public array $raw = [];          // komplette Rohdaten
    public array $bausteine = [];    // normalisiert (wie in ProgramShow)
    public ?array $course = null;    // der ausgewählte Baustein
    public ?array $prev = null;      // vorheriger Baustein (optional)
    public ?array $next = null;      // nächster Baustein (optional)
    public int $index = -1;          // Position in Liste
    public int $total = 0;           // Anzahl Bausteine (zählbar)

    public function mount(string $courseId): void
    {
        $this->courseId = $courseId;

        $user = Auth::user();

        // optionaler API-Refresh wie in ProgramShow
        if (! $user?->person?->last_api_update || $user?->person?->last_api_update->lt(now()->subHours(1))) {
            $user?->person?->apiupdate();
        }

        $this->raw = $user?->person?->programdata ?? [];
        $this->bausteine = $this->normalizeBausteine($this->raw);

        // Kurs anhand ID finden (Fallbacks: slug/kurzbez)
        $selected = collect($this->bausteine)->first(function ($b) {
            // exakte ID?
            if (isset($b['baustein_id']) && (string)$b['baustein_id'] === (string)$this->courseId) {
                return true;
            }
            // slug aus kurzbez/langbez als Alternativen erlauben (z. B. /kurs/FERI oder /kurs/webentwicklung-1)
            $slug = Str::slug(($b['kurzbez'] ?? '') . ' ' . ($b['baustein'] ?? ''));
            return $slug === $this->courseId;
        });

        // Falls nicht gefunden: 404
        if (! $selected) {
            abort(404, 'Baustein nicht gefunden.');
        }

        // Index/Prev/Next bestimmen (nur zählbare Bausteine für die Navigation)
        $zaehlbar = array_values(array_filter($this->bausteine, fn ($b) => !in_array($b['kurzbez'] ?? '', ['FERI', 'PRAK'], true)));
        $this->total = count($zaehlbar);

        $this->index = collect($zaehlbar)->search(fn ($b) => $b['baustein_id'] === ($selected['baustein_id'] ?? null));
        if ($this->index === false) {
            // falls ID nicht matcht (z. B. über slug gefunden), via strict equals auf Objektvergleich
            $this->index = collect($zaehlbar)->search(fn ($b) => $b == $selected);
        }

        $this->course = (array)$selected;
        $this->prev   = ($this->index > 0)                 ? (array)$zaehlbar[$this->index - 1] : null;
        $this->next   = ($this->index !== false && $this->index + 1 < $this->total)
                        ? (array)$zaehlbar[$this->index + 1] : null;

        // kleine Zusatzinfos: Status, Datum hübsch formatiert
        $this->course['status'] = $this->deriveStatus($this->course);
        $this->course['zeitraum_fmt'] = $this->formatZeitraum($this->course);
    }

    private function num(mixed $v): ?float
    {
        if (is_numeric($v)) return $v + 0;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === 'passed') return 100.0;
            if (in_array($v, ['not att', '---', '-'], true)) return null;
            if (is_numeric($v)) return $v + 0;
        }
        return null;
    }

    /** Bausteine wie in ProgramShow normalisieren */
    private function normalizeBausteine(array $raw): array
    {
        return collect($raw['tn_baust'] ?? [])->map(function ($b) {
            return [
                'baustein_id'        => $b['baustein_id'] ?? null,
                'block'              => null,
                'abschnitt'          => null,
                'beginn'             => $b['beginn_baustein'] ?? null,
                'ende'               => $b['ende_baustein'] ?? null,
                'tage'               => $this->num($b['baustein_tage'] ?? null),
                'unterrichtsklasse'  => $b['klassen_co_ks'] ?? null,
                'baustein'           => $b['langbez'] ?? ($b['kurzbez'] ?? '—'),
                'kurzbez'            => $b['kurzbez'] ?? null,
                'schnitt'            => $this->num($b['tn_punkte'] ?? null),
                'punkte'             => $this->num($b['tn_punkte'] ?? null),
                'fehltage'           => $this->num($b['fehltage'] ?? null),
                'klassenschnitt'     => $this->num($b['klassenschnitt'] ?? null),
                'slug'               => Str::slug(($b['kurzbez'] ?? '') . ' ' . ($b['langbez'] ?? '')),
            ];
        })->values()->all();
    }

    private function deriveStatus(array $b): string
    {
        $now   = Carbon::now();
        $start = !empty($b['beginn']) ? Carbon::parse($b['beginn']) : null;
        $end   = !empty($b['ende'])   ? Carbon::parse($b['ende'])   : null;

        if (is_numeric($b['schnitt'])) {
            return ($b['schnitt'] >= 50) ? 'bestanden' : 'abgeschlossen';
        }
        if ($start && $end) {
            if ($now->lt($start)) return 'geplant';
            if ($now->between($start, $end)) return 'aktiv';
            if ($now->gt($end)) return 'abgeschlossen';
        }
        return 'offen';
    }

    private function formatZeitraum(array $b): string
    {
        try {
            $start = !empty($b['beginn']) ? Carbon::parse($b['beginn'])->locale('de')->isoFormat('ll') : '—';
            $end   = !empty($b['ende'])   ? Carbon::parse($b['ende'])->locale('de')->isoFormat('ll')   : '—';
            return "{$start} – {$end}";
        } catch (\Throwable $e) {
            return '—';
        }
    }

    public function render()
    {
        return view('livewire.user.program.course.course-show', [
            'course'   => $this->course,
            'prev'     => $this->prev,
            'next'     => $this->next,
            'index'    => $this->index,
            'total'    => $this->total,
            'bausteine'=> $this->bausteine, // falls du eine Seitenleiste willst
        ])->layout('layouts.app');
    }
}
