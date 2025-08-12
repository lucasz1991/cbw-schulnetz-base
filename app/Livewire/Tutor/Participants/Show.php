<?php

namespace App\Livewire\Tutor\Participants;

use Livewire\Component;
use App\Models\User;

class Show extends Component
{
    public User $participant;

    public function mount(User $participant)
    {
        $this->participant = $participant;
    }

    public function render()
    {
        return view('livewire.tutor.participants.show')->layout("layouts.app-tutor");
    }
}

