<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\UserRequest;

class RequestDetailModal extends Component
{
    public bool $showModal = false;

    /** Aktueller Request (für Blade) */
    public ?UserRequest $request = null;

    protected $listeners = [
        // kommt aus UserRequests: $this->dispatch('open-request-form-edit', id: $id)
        'open-request-form-edit' => 'openById',
    ];

    public function openById($payload): void
    {
        $id = (int) ($payload['id'] ?? $payload);
        $this->request = UserRequest::with(['files'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $this->showModal = true;
    }

    public function close(): void
    {
        $this->reset(['showModal']);
    }

    public function cancel(): void
    {
        if (!$this->request) return;
        if ($this->request->user_id !== Auth::id() || $this->request->status !== 'pending') {
            return;
        }

        $this->request->update([
            'status'     => 'canceled',
            'decided_at' => now(),
        ]);

        $this->dispatch('notify', type: 'success', message: 'Antrag storniert.');
        $this->dispatch('user-request:updated');
        $this->close();
    }

    public function delete(): void
    {
        if (!$this->request) return;

        if ($this->request->user_id !== Auth::id()) {
            return;
        }

        $this->request->delete();
        $this->dispatch('notify', type: 'success', message: 'Antrag gelöscht.');
        $this->dispatch('user-request:deleted');
        $this->close();
    }

    public function render()
    {
        return view('livewire.user.request-detail-modal');
    }
}
