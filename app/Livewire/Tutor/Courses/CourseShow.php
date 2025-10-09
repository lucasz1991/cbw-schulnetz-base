<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class CourseShow extends Component
{
    use WithPagination, WithoutUrlPagination;

    public Course $course;

    public $selectedDate;

    public string $search = '';
    public string $sortBy = 'nachname'; // vorher: 'name'
    public string $sortDir = 'asc';
    public int $perPage = 10;

    protected $listeners = [
        'calendarEventClick' => 'handleCalendarEventClick',
    ];

    public function mount($courseId)
    {
        // passe die Relations an deine tatsächlichen Beziehungsnamen an
        $this->course = Course::with(['tutor', 'dates'])->findOrFail($courseId);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function handleCalendarEventClick($id)
    {
        $this->selectedDate = CourseDay::findOrFail($id);
    }

    public function sort(string $key): void
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
        // Whitelist akzeptierter Sortierfelder (existierende DB-Spalten!)
        $allowedSorts = ['vorname', 'nachname', 'email', 'created_at'];

        return $this->course->participants()
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(function ($qq) use ($term) {
                    // nachname / vorname
                    $qq->where('vorname', 'like', $term)
                       ->orWhere('nachname', 'like', $term)
                       // vollständiger Name "Vorname Nachname"
                       ->orWhereRaw("CONCAT_WS(' ', vorname, nachname) LIKE ?", [$term])
                       // häufig vorhandenes E-Mail-Feld
                       ->orWhere('email', 'like', $term)
                       // falls du email_priv nutzt, kommentier die nächste Zeile rein:
                       //->orWhere('email_priv', 'like', $term)
                       ;
                });
            })
            ->when(true, function ($q) use ($allowedSorts) {
                // Spezialfall: „name“ → nachname, vorname
                if ($this->sortBy === 'name') {
                    $q->orderBy('nachname', $this->sortDir)
                      ->orderBy('vorname', $this->sortDir);
                    return;
                }

                // Nur zulässige Spalten sortieren, sonst Fallback
                $sort = in_array($this->sortBy, $allowedSorts, true) ? $this->sortBy : 'nachname';
                $q->orderBy($sort, $this->sortDir);

                // Bei Sortierung nach Nachname zusätzlich Vorname als Tiebreaker
                if ($sort === 'nachname') {
                    $q->orderBy('vorname', $this->sortDir);
                }
            })
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.tutor.courses.course-show', [
            'participants' => $this->participants,
        ])->layout('layouts.app-tutor');
    }
}
