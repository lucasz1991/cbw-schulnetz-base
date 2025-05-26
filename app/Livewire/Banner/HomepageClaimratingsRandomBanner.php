<?php

namespace App\Livewire\Banner;

use Livewire\Component;
use App\Models\ClaimRating;
use Illuminate\Support\Collection;

class HomepageClaimratingsRandomBanner extends Component
{
    public Collection $claimRatings;

    public function mount()
    {
        $this->claimRatings = ClaimRating::with('insurance')
            ->where('status', 'rated')
            ->inRandomOrder()
            ->take(15)
            ->get();
    }

    public function render()
    {
        return view('livewire.banner.homepage-claimratings-random-banner');
    }
}
