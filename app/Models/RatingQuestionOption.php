<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RatingQuestion;

class RatingQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_question_id',
        'label',
        'value',
        'is_active',
        'order_column',
        'visibility_condition',
    ];

    protected $casts = [
        'visibility_condition' => 'array',
    ];

    public function question()
    {
        return $this->belongsTo(RatingQuestion::class, 'rating_question_id');
    }
}
