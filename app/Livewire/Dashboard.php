<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\ClaimRating;

class Dashboard extends Component
{
    use WithPagination;

    public $userData;
    public $ratingsCount;
    public $verifiedRatingsCount;
    public $pendingRatingsCount;
    public $averageScore;

    protected $listeners = ['refreshParent' => '$refresh'];

    public function render()
    {
        $this->userData = Auth::user();

        $ratings = ClaimRating::where('user_id', $this->userData->id)->latest()->get();

        $this->ratingsCount = $ratings->count();
        $this->verifiedRatingsCount = $ratings->where('is_public', true)->count();
        $this->pendingRatingsCount = $ratings->where('status', 'pending')->count();

        // Durchschnittlicher Score (wenn vorhanden)
        $this->averageScore = $ratings->whereNotNull('rating_score')->avg('rating_score') ?? 0;

        // Paginierte Bewertungen
        $claimRatings = ClaimRating::where('user_id', $this->userData->id)
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        return view('livewire.dashboard', [
            'claimRatings' => $claimRatings,
        ])->layout("layouts.app");
    }
}
