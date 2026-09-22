<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingOutbox extends Model
{
    protected $table = 'coaching_outbox';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime', 'available_at' => 'datetime'];
    public function contract() { return $this->belongsTo(CoachingContract::class, 'coaching_contract_id'); }
}
