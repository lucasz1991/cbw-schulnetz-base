<?php

namespace App\Livewire\Pages;

use App\Models\Insurance;
use Livewire\Component;

class Ranking extends Component
{
    public function render()
    {
        $allInsurances = Insurance::withAvg('claimRatings as avg_score', 'rating_score')
            ->take(5)
            ->orderByDesc('avg_score')
            ->get();

        $top5 = $allInsurances->take(5);
        $flop5 = $allInsurances->sortBy('avg_score')->take(5);

        return view('livewire.pages.ranking', [
            'top5' => $top5,
            'flop5' => $flop5,
            'allInsurances' => $allInsurances,
        ])->layout('layouts.app');
    }
}
