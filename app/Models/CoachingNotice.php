<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingNotice extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['content' => 'array', 'mail_sent_at' => 'datetime', 'dismissed_at' => 'datetime', 'available_at' => 'datetime'];
    public function contract() { return $this->belongsTo(CoachingContract::class, 'coaching_contract_id'); }
}
