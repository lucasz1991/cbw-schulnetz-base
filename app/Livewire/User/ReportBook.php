<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class ReportBook extends Component
{
    public string $date;
    public string $text = '';
    public array $recent = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->loadRecent();
        $this->loadForDate($this->date);
    }

    public function updatedDate($value): void
    {
        $this->loadForDate($value);
    }

    public function save(): void
    {
        if (!class_exists(\App\Models\ReportEntry::class)) {
            $this->dispatch('toast', type: 'warning', message: 'ReportEntry Model noch nicht vorhanden.');
            return;
        }

        $entry = \App\Models\ReportEntry::query()
            ->firstOrNew([
                'user_id'    => Auth::id(),
                'entry_date' => $this->date,
            ]);

        $entry->text = $this->text;
        $entry->save(); 

        $this->dispatch('toast', type: 'success', message: 'Berichtsheft gespeichert.');
        $this->loadRecent();
    }

    protected function loadForDate(string $date): void
    {
        if (!class_exists(\App\Models\ReportEntry::class)) {
            $this->text = '';
            return;
        }

        $entry = \App\Models\ReportEntry::query()
            ->where('user_id', Auth::id())
            ->whereDate('entry_date', $date)
            ->first();

        $this->text = $entry?->text ?? '';
    }

    protected function loadRecent(): void
    {
        if (!class_exists(\App\Models\ReportEntry::class)) {
            $this->recent = [];
            return;
        }

        $this->recent = \App\Models\ReportEntry::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('entry_date')
            ->limit(14)
            ->get(['entry_date', 'text'])
            ->map(fn($r) => [
                'date' => Carbon::parse($r->entry_date)->toDateString(),
                'excerpt' => str(\Illuminate\Support\Str::of($r->text)->stripTags())->limit(80),
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.user.report-book');
    }
}
