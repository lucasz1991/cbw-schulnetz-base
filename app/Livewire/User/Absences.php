<?php

namespace App\Livewire\User;

use Livewire\Component;

class Absences extends Component
{
    public bool $showModal = false;

    // Formularfelder (minimal; befülle nach Bedarf)
    public ?string $klasse = null;
    public bool $fehltag = false;
    public ?string $fehlDatum = null;
    public ?string $fehlUhrGek = null;
    public ?string $fehlUhrGeg = null;
    public ?string $abw_grund = null;
    public ?string $grund_item = null;
    public ?string $begruendung = null;

    protected $listeners = [
        // Event aus der Liste: $this->dispatch('open-request-form', type: 'absence');
        'open-request-form' => 'handleOpen',
        // Optional gezielt nur für dieses Modul:
        'open-absence-form' => 'open',
    ];

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'absence') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void
    {
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','fehltag','fehlDatum','fehlUhrGek','fehlUhrGeg',
            'abw_grund','grund_item','begruendung',
        ]);
        $this->resetErrorBag();
    }

    // Optional: später save() etc. – aktuell nur UI/Modal
    // public function save() { ... }

    public function render()
    {
        return view('livewire.user.absences')->layout('layouts.app');
    }
}
