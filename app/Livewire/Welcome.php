<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Welcome extends Component
{

    



    public function mount()
    {
        
    }
    public function render()
    {           
        return view('livewire.welcome')->layout('layouts/app');
    }
}