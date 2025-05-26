<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Insurance;


class InsuranceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'weight',
        'is_active',
        'order_id',
    ];

    public function insurances()
    {
        return $this->belongsToMany(Insurance::class, 'insurance_insurance_type')
                    ->withPivot('order_column')
                    ->orderBy('insurance_insurance_type.order_column');
    }
    public function subtypes()
    {
        return $this->belongsToMany(InsuranceSubtype::class, 'insurance_type_insurance_subtype')
                    ->withPivot('order_id')
                    ->orderBy('insurance_type_insurance_subtype.order_id');
    }

    public function insuranceSubtypes()
    {
        return $this->belongsToMany(InsuranceSubtype::class, 'insurance_type_insurance_subtype')
                    ->withPivot('order_id')
                    ->orderBy('pivot_order_id');
    }
    
 
}
