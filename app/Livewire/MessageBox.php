<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Message;
use Livewire\WithPagination;
use App\Models\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function markAsRead(int $messageId): void
    {
        $message = auth()->user()->receivedMessages()->find($messageId);

        if (! $message) {
            return;
        }

        if ((int) $message->status !== 2) {
            $message->update(['status' => 2]);
        }

        $this->dispatch('refreshComponent');
    }

    public function deleteMessage(int $messageId): void
    {
        $message = auth()->user()->receivedMessages()
            ->with('files')
            ->find($messageId);

        if (! $message) {
            return;
        }

        foreach ($message->files as $file) {
            $file->delete();
        }

        $message->delete();

        if ($this->selectedMessage && (int) $this->selectedMessage->id === $messageId) {
            $this->selectedMessage = null;
            $this->showMessageModal = false;
        }

        $this->dispatch('refreshComponent');
    }

    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return $file->download(); // 👈 zentral im Model
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
