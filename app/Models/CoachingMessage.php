<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingMessage extends Model
{
    protected $guarded = ['id'];
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
