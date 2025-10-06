<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\UserRequest;

class UserRequests extends Component
{
    use WithPagination;

    public string $filterStatus = 'all';        // all|pending|approved|rejected|canceled
    public string $filterType   = 'all';        // all|makeup|external_makeup|absence|general
    public string $search       = '';
    public int $perPage         = 10;

    protected $queryString = [
        'filterStatus' => ['except' => 'all'],
        'filterType'   => ['except' => 'all'],
        'search'       => ['except' => ''],
    ];

    protected $listeners = [
        // von Unter-Modulen senden nach Save/Delete:
        'user-request:updated' => '$refresh',
        'user-request:created' => '$refresh',
        'user-request:deleted' => '$refresh',
    ];

    public function updatingSearch()     { $this->resetPage(); }
    public function updatingFilterType() { $this->resetPage(); }
    public function updatingFilterStatus(){ $this->resetPage(); }

    public function openCreate(string $type): void
    {
        // nur Events emittieren, KEINE Formulare hier
        $this->dispatch('open-request-form', type: $type); // z.B. 'makeup' | 'external_makeup' | 'absence' | 'general'
    }

    public function openEdit(int $id): void
    {
        $this->dispatch('open-request-form-edit', id: $id);
    }

    public function cancel(int $id): void
    {
        $req = UserRequest::where('user_id', Auth::id())->findOrFail($id);
        if ($req->status === 'pending') {
            $req->update(['status' => 'canceled', 'decided_at' => now()]);
            $this->dispatch('notify', type: 'success', message: 'Antrag storniert.');
            $this->dispatch('user-request:updated');
        }
    }

    public function delete(int $id): void
    {
        $req = UserRequest::where('user_id', Auth::id())->findOrFail($id);
        $req->delete();
        $this->dispatch('notify', type: 'success', message: 'Antrag gelöscht.');
        $this->dispatch('user-request:deleted');
    }

    public function getRequestsProperty()
    {
        return UserRequest::where('user_id', Auth::id())
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== 'all',   fn($q) => $q->where('type', $this->filterType))
            ->when($this->search, function ($q) {
                $q->where(function ($w) {
                    $w->where('title', 'like', '%'.$this->search.'%')
                      ->orWhere('message', 'like', '%'.$this->search.'%')
                      ->orWhere('module_code', 'like', '%'.$this->search.'%')
                      ->orWhere('instructor_name', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.user.user-requests', [
            'requests' => $this->requests,
        ]);
    }
}
