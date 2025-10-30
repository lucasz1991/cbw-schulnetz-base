<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Message;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MessageBox extends Component
{
    use WithPagination;

    public $selectedMessage;
    public $showMessageModal = false;
    public $loadedPages = 1;

    public string $search = ''; 

    protected $listeners = [
        'refreshComponent' => '$refresh',
    ];

    public function mount()
    {
        $this->dispatch('refreshComponent');
    }

    public function updatingSearch()    { $this->resetPage(); }   // bei Suche neue Seite
    public function loadMore()          { $this->loadedPages++; } // "Mehr laden"

    public function showMessage($messageId)
    {
        $this->selectedMessage = auth()->user()->receivedMessages()
            ->with(['files', 'sender']) // sicherstellen
            ->find($messageId);

        if ($this->selectedMessage) {
            $this->selectedMessage->update(['status' => 2]); // gelesen
            $this->showMessageModal = true;
        }
        $this->dispatch('refreshComponent');
    }

    public function render()
    {
        $base = auth()->user()->receivedMessages()
            ->with(['sender:id,name,role,profile_photo_path']) 
            ->withCount('files')                 
            ->orderByDesc('created_at');

        if (filled($this->search)) {
            $s = '%'.trim($this->search).'%';
            $base->where(function ($q) use ($s) {
                $q->where('subject', 'like', $s)
                  ->orWhere('message', 'like', $s)
                  ->orWhereHas('sender', fn($qs) => $qs->where('name', 'like', $s));
            });
        }

        $messages = $base->paginate(12 * $this->loadedPages);

        if (auth()->check() && auth()->user()->role === 'tutor') {
            return view('livewire.message-box', compact('messages'))->layout('layouts/app-tutor');
        }
        return view('livewire.message-box', compact('messages'))->layout('layouts/app');
    }
}

