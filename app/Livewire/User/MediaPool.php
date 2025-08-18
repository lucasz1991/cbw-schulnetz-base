<?php

namespace App\Livewire\User;

use Livewire\Component;

class MediaPool extends Component
{

    public function mount()
    {
       sleep(1);
    }



    public function render()
    {
        return view('livewire.user.media-pool');
    }
}
