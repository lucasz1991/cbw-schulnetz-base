<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'started_at',
        'ends_at',
        'is_active',
        'payment_method',
        'interval',
        'payment_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'payment_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
