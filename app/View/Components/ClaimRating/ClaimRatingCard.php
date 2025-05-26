<?php

namespace App\View\Components\ClaimRating;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ClaimRatingCard extends Component
{
    public $rating;

    public function __construct($rating)
    {
        $this->rating = $rating;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.claim-rating.claim-rating-card');
    }
}
