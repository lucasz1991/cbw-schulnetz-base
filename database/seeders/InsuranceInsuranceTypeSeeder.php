<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Insurance;
use App\Models\InsuranceType;

class InsuranceInsuranceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $insuranceIds = Insurance::pluck('id')->toArray();
        $typeIds = InsuranceType::pluck('id')->toArray();

        foreach ($insuranceIds as $insuranceId) {
            $insurance = Insurance::find($insuranceId);
            if ($insurance) {
                $syncData = [];
                foreach ($typeIds as $index => $typeId) {
                    $syncData[$typeId] = ['order_column' => $index + 1];
                }
                $insurance->insuranceTypes()->syncWithoutDetaching($syncData);
            }
        }
    }
}
