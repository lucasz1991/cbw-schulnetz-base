<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Insurance;
use App\Models\InsuranceType;
use App\Models\InsuranceSubtype;

class Insurances extends Component
{
    use WithPagination;

    public $search;
    public $insuranceTypes = [];
    public $insuranceType = null;
    public $insuranceSubTypes = [];
    public $insuranceSubType = null;
    public $perPage = 9;
    public $pages = 1;


    public $sort;
    public $minRatingCount;
    public $minAvgScore;
    public $onlyActive;


    protected $queryString = [
        'search' => ['except' => ''],
        'insuranceType' => ['except' => ''],
        'insuranceSubType' => ['except' => '']
    ];

    public function mount()
    {
        $this->insuranceTypes = InsuranceType::all();
        $this->insuranceSubTypes = InsuranceSubtype::all();
        $this->search = '';
        $this->pages = 1;
        $this->sort = 'score_desc';
        $this->minRatingCount = 1;
    }


    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingInsuranceType()
    {
        $this->resetPage();
    }

    public function updatingInsuranceSubType()
    {
        $this->resetPage();
    }

    public function loadMore()
    {
        $this->pages++;
    }

    public function resetFilters()
    {
        $this->reset([
            'insuranceType',
            'insuranceSubType',
            'search',
            'minRatingCount',
            'minAvgScore',
            'onlyActive',
        ]);
    }

    public function render()
    {
        $insurances = Insurance::query()
            ->withCount('claimRatings')
            ->withAvg('claimRatings', 'rating_score')
            ->whereHas('claimRatings', function ($query) {
                $query->where('status', 'rated');
            })
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->insuranceType, function ($query) {
                $query->whereHas('insuranceTypes', function ($query) {
                    // Klarer Verweis auf die Tabelle insurance_types
                    $query->where('insurance_types.id', (int) $this->insuranceType);
                });
            })
            ->when($this->insuranceSubType, function ($query) {
                $query->whereHas('insuranceTypes.insuranceSubTypes', function ($subQuery) {
                    $subQuery->where('insurance_subtypes.id', (int) $this->insuranceSubType);
                });
            })
            ->when($this->sort === 'score_desc', fn ($q) => $q->orderByDesc('claim_ratings_avg_rating_score'))
            ->when($this->sort === 'score_asc', fn ($q) => $q->orderBy('claim_ratings_avg_rating_score'))
            ->when($this->sort === 'count_desc', fn ($q) => $q->orderByDesc('claim_ratings_count'))
            ->when($this->sort === 'count_asc', fn ($q) => $q->orderBy('claim_ratings_count'))
            ->when($this->minRatingCount, fn ($q) => $q->having('claim_ratings_count', '>=', $this->minRatingCount))
            ->when($this->minAvgScore, fn ($q) => $q->having('claim_ratings_avg_rating_score', '>=', $this->minAvgScore))
            ->paginate($this->perPage*$this->pages);
    

    
        return view('livewire.pages.insurances', [
            'insurances' => $insurances,
            'insuranceTypes' => $this->insuranceTypes,
            'insuranceSubTypes' => $this->insuranceSubTypes,
        ])->layout('layouts.app');
    }
    
}
