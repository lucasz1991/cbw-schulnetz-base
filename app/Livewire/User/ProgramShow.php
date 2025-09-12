<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class ProgramShow extends Component
{
    use WithPagination;

    /** Eingeloggter User (fürs View, falls nötig) */
    public $userData;

    /** Programm-/Teilnehmerdaten (aus DB oder Fallback) */
    public array $teilnehmerDaten = [];

    protected $listeners = ['refreshParent' => '$refresh'];

    public function mount(): void
    {
        $this->userData = Auth::user();

        // 1) Versuche programdata zu laden
        $raw = $this->userData?->person?->programdata ?? null;

        // 2) In Array wandeln oder Fallback
        if (is_array($raw)) {
            $this->teilnehmerDaten = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $this->teilnehmerDaten = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : [];
        } else {
            $this->teilnehmerDaten = [];
        }
    }

    public function render()
    {
        // Kein State-Ändern hier!
        return view('livewire.user.program-show', [
            'user' => $this->userData,
            'data' => $this->teilnehmerDaten,
        ]);
    }
}
