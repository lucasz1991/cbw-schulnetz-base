<?php

namespace App\View\Components\Insurance;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InsuranceCard extends Component
{
    public $insurance;

    public function __construct($insurance)
    {
        $this->insurance = $insurance;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.insurance.insurance-card');
    }
}