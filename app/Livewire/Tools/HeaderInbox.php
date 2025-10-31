<?php

namespace App\Livewire\Tools;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HeaderInbox extends Component
{
    public int $unreadMessagesCount = 0;
    public $receivedMessages;            // Collection (kleine Liste)
    public $selectedMessage = null;      // App\Models\Message|null
    public bool $showMessageModal = false;

    public function mount()
    {
        $this->loadInbox();
    }

    public function loadInbox(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->unreadMessagesCount = 0;
            $this->receivedMessages = collect();
            return;
        }

        // kleine Liste für das Dropdown (z.B. letzte 6)
        $this->receivedMessages = $user->receivedMessages()
            ->with(['sender:id,name,role,profile_photo_path'])
            ->withCount('files')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        $this->unreadMessagesCount = $user->receivedMessages()
            ->where('status', 1)
            ->count();
    }

    public function showMessage(int $messageId): void
    {
        $user = Auth::user();
        if (!$user) return;

        // Nachricht holen (inkl. files & sender fürs Modal)
        $this->selectedMessage = $user->receivedMessages()
            ->with(['files', 'sender'])
            ->find($messageId);

        if ($this->selectedMessage) {
            // als gelesen markieren
            if ((int)$this->selectedMessage->status === 1) {
                $this->selectedMessage->update(['status' => 2]);
            }

            // Modal öffnen
            $this->showMessageModal = true;

            // Zähler/Liste aktualisieren
            $this->loadInbox();
        }
    }

    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return $file->download(); // 👈 zentral im Model
    }

    // Optional: Button "Alle ansehen" könnte hier auch nur route() sein; kein Handler nötig.
    public function render()
    {
        return view('livewire.tools.header-inbox');
    }
}
