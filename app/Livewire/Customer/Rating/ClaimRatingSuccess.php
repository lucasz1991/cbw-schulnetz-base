<?php

namespace App\Livewire\Customer\Rating;

use Livewire\Component;
use App\Models\ClaimRating;

class ClaimRatingSuccess extends Component
{
    public ClaimRating $claimRating;

    public function mount(string $hash)
    {
        $this->claimRating = ClaimRating::where('verification_hash', $hash)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.customer.rating.claim-rating-success')->layout('layouts/app');
    }
}
