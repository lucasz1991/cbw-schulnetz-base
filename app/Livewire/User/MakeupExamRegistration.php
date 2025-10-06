<?php

namespace App\Livewire\User;

use Livewire\Component;

class MakeupExamRegistration extends Component
{
    public bool $showModal = false;

    // Formularfelder
    public ?string $klasse = null;
    public ?string $wiederholung = null;   // wiederholung_1 | wiederholung_2
    public ?string $nachKlTermin = null;   // Unix-Timestamp als String
    public ?string $nKlBaust = null;       // Baustein
    public ?string $nKlDozent = null;      // Dozent
    public ?string $nKlOrig = null;        // Y-m-d
    public ?string $grund = null;          // unter51 | krankMitAtest | krankOhneAtest

    protected $listeners = [
        // Von der Liste gesendet:
        'open-request-form' => 'handleOpen',
        // Optional: gezielt nur für dieses Modul
        'open-makeup-form'  => 'open',
    ];

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'makeup') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void  { $this->showModal = true; }
    public function close(): void { $this->showModal = false; }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','wiederholung','nachKlTermin','nKlBaust',
            'nKlDozent','nKlOrig','grund',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.user.makeup-exam-registration')->layout('layouts.app');
    }
}
