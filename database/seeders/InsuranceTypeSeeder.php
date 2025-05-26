<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InsuranceType;
use Illuminate\Support\Str;


class InsuranceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Personenversicherungen',
            'Sachversicherungen',
            'Haftpflichtversicherungen',
            'Vermögensversicherungen',
            'Gewerbe- und Unternehmensversicherungen',
            'Spezialversicherungen',
            'Zusatzversicherungen',
            'Weitere Versicherungen',
            'Ergänzungen und spezialisierte Versicherungen',
        ];

        foreach ($types as $name) {
            InsuranceType::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => '',
            ]);
        }
    }
}
