<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsuranceWithTypesSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InsuranceTypeSeeder::class,
            InsuranceSeeder::class,
            InsuranceSubtypeSeeder::class,
            InsuranceInsuranceTypeSeeder::class,
            InsuranceTypeInsuranceSubtypeSeeder::class,
            RatingQuestionSeeder::class,
            InsuranceSubtypeRatingQuestionSeeder::class,
            // WebPagesSeeder::class, 
        ]);
    }

}
