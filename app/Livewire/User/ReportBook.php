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
  
    public ?string $massnahmeId = null;

    public string $date;
    public ?int $reportBookId = null;

    public ?string $title = null;
    public string $text = '';
    public int $status = 0; // 0 = Entwurf, 1 = Eingereicht

    public array $recent = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();

        // ReportBook sicherstellen (pro User & Maßnahme)
        $book = $this->getOrCreateReportBook();
        $this->reportBookId = $book->id;

        $this->loadRecent();
        $this->loadForDate($this->date);
    }

public function selectDate(string $date): void
{
    $this->date = $date;
    $this->loadForDate($date);
}

public function updatedDate($value): void
{
    $this->loadForDate($value);
}


    /**
     * Speichern als Entwurf (status = 0)
     */
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
            'status' => 0,
        ]);

        // Wenn vorher eingereicht, aber jetzt als Entwurf gespeichert werden soll:
        if ($entry->isDirty('status') && $entry->status === 0) {
            // submitted_at bleibt als Historie bestehen; kann man auch nullen, wenn gewünscht
        }

        $entry->save();

        $this->status = $entry->status;

        $this->dispatch('toast', type: 'success', message: 'Berichtsheft gespeichert (Entwurf).');
        $this->loadRecent();
    }

    /**
     * Einreichen (status = 1, submitted_at = now)
     */
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
            'title'       => $this->title,
            'text'        => $this->text,
            'status'      => 1,
            'submitted_at'=> now(),
        ]);

        $entry->save();

        $this->status = $entry->status;

        $this->dispatch('toast', type: 'success', message: 'Eintrag eingereicht.');
        $this->loadRecent();
    }

    protected function loadForDate(string $date): void
    {
        if (!$this->reportBookId) {
            $this->title = null;
            $this->text  = '';
            $this->status = 0;
            return;
        }

        $entry = ReportBookEntry::query()
            ->where('report_book_id', $this->reportBookId)
            ->whereDate('entry_date', $date)
            ->first();

        $this->title  = $entry?->title ?? null;
        $this->text   = $entry?->text ?? '';
        $this->status = (int) ($entry?->status ?? 0);
    }




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

    protected function getOrCreateReportBook(): ReportBookModel
    {
        $userId = Auth::id();

        $book = ReportBookModel::query()
            ->firstOrCreate(
                [
                    'user_id'      => $userId,
                    'massnahme_id' => $this->massnahmeId, // kann null sein
                ],
                [
                    'title'       => 'Mein Berichtsheft',
                    'description' => $this->massnahmeId ? "Maßnahme: {$this->massnahmeId}" : null,
                    'start_date'  => null,
                    'end_date'    => null,
                ]
            );

        return $book;
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
        return view('livewire.user.report-book');
    }
}
