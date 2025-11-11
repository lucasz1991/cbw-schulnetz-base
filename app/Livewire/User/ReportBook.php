<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\ReportBook as ReportBookModel;
use App\Models\ReportBookEntry;

class ReportBook extends Component
{
    /** Maßnahme-Context (optional; kann null sein) */
    public ?string $massnahmeId = null;

    /** Aktueller Tag & aktives ReportBook */
    public string $date;
    public ?int $reportBookId = null;

    /** Formularfelder */
    public ?string $title = null;
    public string $text = '';
    /** 0 = Entwurf, 1 = Fertig */
    public int $status = 0;

    /** Sidebar */
    public array $recent = [];

    /** UI-Flags */
    public bool $isDirty = false;
    public bool $hasDraft = false;

    /** interner Snapshot für Dirty-Check */
    protected ?string $initialHash = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();

        $book = $this->getOrCreateReportBook();
        $this->reportBookId = $book->id;

        $this->loadRecent();
        $this->loadForDate($this->date);
    }

    /** Helper: aktuellen Formularzustand hashen */
    protected function curHash(): string
    {
        return md5(($this->title ?? '') . '|' . ($this->text ?? ''));
    }

    protected function recomputeFlags(): void
    {
        $this->isDirty = $this->curHash() !== ($this->initialHash ?? '');
        // $hasDraft wird in loadForDate() aus der DB gesetzt
    }

    /** Sidebar-Klick */
    public function selectDate(string $date): void
    {
        $this->date = $date;
        $this->loadForDate($date);
    }

    /** Date-Input (rechts) */
    public function updatedDate($value): void
    {
        $this->loadForDate($value);
    }

    /** Reagiere auf Eingaben (Title/Text) für Dirty-Flag */
    public function updated($name, $value): void
    {
        if (in_array($name, ['title', 'text'])) {
            $this->recomputeFlags();
        }
    }

    /** Speichern als Entwurf */
    public function save(): void
    {
        if (!$this->reportBookId) {
            $this->dispatch('toast', type: 'warning', message: 'ReportBook nicht gefunden.');
            return;
        }

        $entry = ReportBookEntry::query()->firstOrNew([
            'report_book_id' => $this->reportBookId,
            'entry_date'     => $this->date,
        ]);

        $entry->fill([
            'title'  => $this->title,
            'text'   => $this->text,
            'status' => 0, // Entwurf
        ])->save();

        $this->status = 0;
        $this->hasDraft = true;

        // Nach Save ist der aktuelle Stand Basis
        $this->initialHash = $this->curHash();
        $this->recomputeFlags();

        $this->dispatch('toast', type: 'success', message: 'Entwurf gespeichert.');
        $this->loadRecent();
    }

    /** Fertigstellen */
    public function submit(): void
    {
        if (!$this->reportBookId) {
            $this->dispatch('toast', type: 'warning', message: 'ReportBook nicht gefunden.');
            return;
        }

        $entry = ReportBookEntry::query()->firstOrNew([
            'report_book_id' => $this->reportBookId,
            'entry_date'     => $this->date,
        ]);

        $entry->fill([
            'title'        => $this->title,
            'text'         => $this->text,
            'status'       => 1,       // Fertig
            'submitted_at' => now(),
        ])->save();

        $this->status = 1;
        $this->hasDraft = false;

        $this->initialHash = $this->curHash();
        $this->recomputeFlags();

        $this->dispatch('toast', type: 'success', message: 'Eintrag fertiggestellt.');
        $this->loadRecent();
    }

    /** Tagesdaten laden */
    protected function loadForDate(string $date): void
    {
        if (!$this->reportBookId) {
            $this->title = null;
            $this->text  = '';
            $this->status = 0;
            $this->hasDraft = false;
            $this->initialHash = $this->curHash();
            $this->recomputeFlags();
            return;
        }

        $entry = ReportBookEntry::query()
            ->where('report_book_id', $this->reportBookId)
            ->whereDate('entry_date', $date)
            ->first();

        $this->title  = $entry?->title ?? null;
        $this->text   = $entry?->text ?? '';
        $this->status = (int) ($entry?->status ?? 0);
        $this->hasDraft = (bool) $entry && (int) $entry->status === 0;

        // Snapshot für Dirty-Check
        $this->initialHash = $this->curHash();
        $this->recomputeFlags();

        // Falls du den Toast-Editor hart füttern willst:
        // $this->dispatch('rb-editor-set', content: $this->text);
    }

    /** Sidebar „Letzte Einträge“ */
    protected function loadRecent(): void
    {
        if (!$this->reportBookId) {
            $this->recent = [];
            return;
        }

        $this->recent = ReportBookEntry::query()
            ->where('report_book_id', $this->reportBookId)
            ->orderByDesc('entry_date')
            ->limit(5)
            ->get(['entry_date', 'title', 'text', 'status'])
            ->map(function ($r) {
                return [
                    'date'    => Carbon::parse($r->entry_date)->toDateString(),
                    'title'   => $r->title,
                    'status'  => (int) $r->status,
                    'excerpt' => Str::of(strip_tags($r->text ?? ''))->limit(80)->value(),
                ];
            })
            ->toArray();
    }

    /** Ein Heft (pro User & Maßnahme) sicherstellen */
    protected function getOrCreateReportBook(): ReportBookModel
    {
        return ReportBookModel::query()
            ->firstOrCreate(
                [
                    'user_id'      => Auth::id(),
                    'massnahme_id' => $this->massnahmeId,
                ],
                [
                    'title'       => 'Mein Berichtsheft',
                    'description' => $this->massnahmeId ? "Maßnahme: {$this->massnahmeId}" : null,
                ]
            );
    }

    /** Placeholder während lazy load */
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
        return view('livewire.user.report-book');
    }
}
