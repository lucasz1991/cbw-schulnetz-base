<?php

namespace App\Livewire\Tutor;

use Livewire\Component;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class CourseList extends Component
{
    public $search = '';

    public function mount()
    {

    }


    public function render()
    {
        return view('livewire.tutor.course-list')->layout("layouts.app-tutor");
    }
}
