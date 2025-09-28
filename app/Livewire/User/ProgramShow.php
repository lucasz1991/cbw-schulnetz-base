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
        $this->teilnehmerDaten = $this->userData?->person?->programdata ?? [];
    }

    public function render()
    {
        return view('livewire.user.program-show', [
            'user' => $this->userData,
            'data' => $this->teilnehmerDaten,
        ]);
    }
}
