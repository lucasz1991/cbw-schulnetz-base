<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingPlan extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'coaching_contract_id' => 'integer', 'participant_person_id' => 'integer', 'tutor_person_id' => 'integer',
        'items' => 'array', 'revision' => 'integer', 'confirmed_at' => 'datetime',
        'tutor_confirmed_at' => 'datetime', 'participant_confirmed_at' => 'datetime',
    ];
    public function contract() { return $this->belongsTo(CoachingContract::class, 'coaching_contract_id'); }
}
