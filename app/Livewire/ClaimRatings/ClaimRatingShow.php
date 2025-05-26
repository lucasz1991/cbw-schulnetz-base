<?php

namespace App\Livewire\ClaimRatings;

use Livewire\Component;
use App\Models\ClaimRating;

class ClaimRatingShow extends Component
{
    public ClaimRating $claimRating;

    public function mount(ClaimRating $claimRating)
    {
        $this->claimRating = $claimRating;
    }

    public function render()
    {
        return view('livewire.claim-ratings.claim-rating-show')
            ->layout('layouts.app');
    }
}
