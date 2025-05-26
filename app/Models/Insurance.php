<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InsuranceType;
use App\Models\InsuranceSubType;

class Insurance extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'initials',
        'color',
        'is_active',
        'order_id',
    ];

    public function insuranceTypes()
    {
        return $this->belongsToMany(InsuranceType::class, 'insurance_insurance_type')
                    ->withPivot('order_column')
                    ->orderBy('insurance_insurance_type.order_column');
    }

    public function insuranceSubTypes()
    {
        return $this->belongsToMany(InsuranceSubType::class, 'insurance_insurance_type')
            ->withPivot('order_column');
    }

    public function avgRatingDuration()
    {
        return round(
            $this->claimRatings->map(function ($rating) {
                return $rating->ratingDuration();
            })->filter()->avg(),
            1
        );
    }

    public function claimRatings()
    {
        return $this->hasMany(ClaimRating::class);
    }
    public function ratings_avg_score()
    {
        return $this->hasMany(ClaimRating::class)->avg('rating_score');
    }
    public function ratings_count()
    {
        return $this->hasMany(ClaimRating::class)->count();
    }
}
