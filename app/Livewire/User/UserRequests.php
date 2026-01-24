<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\UserRequest;

class UserRequests extends Component
{
    use WithPagination;

    public string $filterStatus = 'all';
    public string $filterType   = 'all';
    public string $search       = '';
    public int $perPage         = 10;

    protected $queryString = [
        'filterStatus' => ['except' => 'all'],
        'filterType'   => ['except' => 'all'],
        'search'       => ['except' => ''],
    ];

    protected $listeners = [
        'user-request:updated' => '$refresh',
        'user-request:created' => '$refresh',
        'user-request:deleted' => '$refresh',
    ];

    public function updatingSearch()      { $this->resetPage(); }
    public function updatingFilterType()  { $this->resetPage(); }
    public function updatingFilterStatus(){ $this->resetPage(); }

    public function openCreate(string $type): void
    {
        // z. B. 'makeup' | 'external_makeup' | 'absence' | 'general'
        $this->dispatch('open-request-form', type: $type);
    }

    public function openEdit(int $id): void
    {
        $this->dispatch('open-request-form-edit', id: $id);
    }

    public function cancel(int $id): void
    {
        $req = UserRequest::where('user_id', Auth::id())->findOrFail($id);
        if ($req->status === 'pending') {
            $req->update([
                'status'     => 'canceled',
                'decided_at' => now(),
            ]);
            $this->dispatch('toast', [
                'type' => 'success',
                'title'=> 'Erfolgreich abgebrochen',
                'text' => 'Deine Anfrage wurde abgebrochen.',
            ]);
            $this->dispatch('user-request:updated');
        }
    }

    public function delete(int $id): void
    {
        $req = UserRequest::where('user_id', Auth::id())->findOrFail($id);
        $req->delete();
        $this->dispatch('toast', [
            'type' => 'success',
            'title'=> 'Erfolgreich gelöscht',
            'text' => 'Deine Anfrage wurde gelöscht.',
        ]);
        $this->dispatch('user-request:deleted');
    }

    public function getRequestsProperty()
    {
        return UserRequest::where('user_id', Auth::id())
            ->when($this->filterStatus !== 'all', fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== 'all',   fn($q) => $q->where('type', $this->filterType))
            ->when($this->search, function ($q) {
                $s = '%'.$this->search.'%';
                $q->where(function ($w) use ($s) {
                    $w->where('title', 'like', $s)
                      ->orWhere('message', 'like', $s)
                      ->orWhere('class_code', 'like', $s)
                      ->orWhere('module_code', 'like', $s)
                      ->orWhere('instructor_name', 'like', $s)
                      ->orWhere('reason', 'like', $s)
                      ->orWhere('reason_item', 'like', $s)
                      ->orWhere('certification_key', 'like', $s)
                      ->orWhere('certification_label', 'like', $s);
                });
            })
            // sortiere primär nach submitted_at, sonst created_at
            ->orderByRaw('COALESCE(submitted_at, created_at) DESC')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.user.user-requests', [
            'requests' => $this->requests,
        ])->layout("layouts.app");
    }
}
