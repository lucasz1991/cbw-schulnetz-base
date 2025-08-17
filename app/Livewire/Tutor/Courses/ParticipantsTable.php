<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class ParticipantsTable extends Component
{
    use WithPagination, WithoutUrlPagination;

    public int $courseId;
    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';
    public int $perPage = 10;

    public function mount(int $courseId)
    {
        $this->courseId = $courseId;
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function sort(string $key)
    {
        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function getParticipantsProperty()
    {
        return Course::findOrFail($this->courseId)
            ->participants()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.tutor.courses.participants-table', [
            'participants' => $this->participants,
        ]);
    }
}
