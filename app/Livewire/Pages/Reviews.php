<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use App\Models\ClaimRating; // Beispielmodell für Bewertungen
use App\Models\Insurance;   // Beispielmodell für Versicherungen

class Reviews extends Component
{
    public $ratings;
    public $average;
    public $totalCount;

    public function mount()
    {
        $this->ratings = ClaimRating::with('insurance')->latest()->take(10)->get();
        $this->average = round(ClaimRating::avg('rating_score'), 2);
        $this->totalCount = ClaimRating::count();
    }

    public function render()
    {
        return view('livewire.pages.reviews')->layout('layouts.app');
    }
}
