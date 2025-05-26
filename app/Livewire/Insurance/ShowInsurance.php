<?php

namespace App\Livewire\Insurance;

use Livewire\Component;
use App\Models\Insurance;
use App\Models\ClaimRating;

class ShowInsurance extends Component
{
    public Insurance $insurance;
    public $claimRatings;

    public function mount(Insurance $insurance)
    {
        $this->insurance = $insurance;
        $this->claimRatings = $insurance->claimRatings()
        ->latest()         // sortiert nach created_at DESC
        ->take(2)          // nur 3 Bewertungen
        ->get();
    }

    public function render()
    {
        return view('livewire.insurance.show-insurance')->layout('layouts.app');
    }
}
