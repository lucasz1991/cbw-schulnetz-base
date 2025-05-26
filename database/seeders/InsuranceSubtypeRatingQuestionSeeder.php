<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InsuranceSubtype;
use App\Models\RatingQuestion;

class InsuranceSubtypeRatingQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $types = InsuranceSubtype::all();
        $questions = RatingQuestion::all();

        if ($types->isEmpty() || $questions->isEmpty()) {
            return;
        }

        foreach ($types as $type) {
            $syncData = [];

            foreach ($questions as $index => $question) {
                $syncData[$question->id] = [
                    'order_column' => $index,
                    'visibility_conditions' => null, // Kann später gesetzt werden
                ];
            }

            $type->ratingQuestions()->sync($syncData);
        }
    }
}
