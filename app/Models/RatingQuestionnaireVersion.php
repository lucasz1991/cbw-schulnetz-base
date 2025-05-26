<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InsuranceType;

class RatingQuestionnaireVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_subtype_id',
        'version_number',
        'snapshot',
        'is_active',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'is_active' => 'boolean',
    ];

    public function insuranceSubtype()
    {
        return $this->belongsTo(InsuranceSubtype::class);
    }
}
