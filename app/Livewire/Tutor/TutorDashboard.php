<?php

namespace App\Livewire\Tutor;

use App\Support\CoachingTutorDashboard;
use Livewire\Component;

class TutorDashboard extends Component
{
    public function render()
    {
        return view('livewire.tutor.tutor-dashboard', [
            'coachingDashboard' => app(CoachingTutorDashboard::class)->build(auth()->user()),
        ])->layout('layouts.app-tutor');
    }
}
