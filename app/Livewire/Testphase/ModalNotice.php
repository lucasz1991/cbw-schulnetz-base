<?php

namespace App\Livewire\Testphase;

use Livewire\Component;
use Livewire\Attributes\Session;

class ModalNotice extends Component
{
    /**
     * Indicates whether the preflight modal should be shown.
     *
     * @var bool
     */ 
    #[Session] 
    public bool $showPreflightModal = true;

    public function hide()
    {
        $this->showPreflightModal = false;
    }

    public function render()
    {
        return view('livewire.testphase.modal-notice');
    }
}
