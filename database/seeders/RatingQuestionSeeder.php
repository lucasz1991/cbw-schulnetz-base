<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RatingQuestion;

class RatingQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'title' => 'Fairness',
                'question_text' => 'Wie fair war die Entscheidung der Versicherung aus Ihrer Sicht?',
                'type' => 'rating',
                'is_required' => true,
                'meta' => ['scale' => 5],
                'help_text' => '1 = unfair, 5 = sehr fair',
                'default_value' => 3,
                'is_active' => true,
                'frontend_title' => 'Bewertung der Fairness',
                'frontend_description' => 'War die Entscheidung gerechtfertigt?',
                'weight' => 8,
                'input_constraints' => ['min' => 1, 'max' => 5],
                'read_only' => false,
                'tags' => ['fairness', 'entscheidung'],
            ],
            [
                'title' => 'Service-Kommentar',
                'question_text' => 'Wie empfanden Sie den Kundenservice?',
                'type' => 'textarea',
                'is_required' => false,
                'meta' => [],
                'help_text' => 'Geben Sie Ihre Eindrücke zum Service wieder.',
                'default_value' => null,
                'is_active' => true,
                'frontend_title' => 'Kundenservice',
                'frontend_description' => 'Ihre Erfahrung mit der Kundenbetreuung',
                'weight' => 3,
                'input_constraints' => ['maxLength' => 500],
                'read_only' => false,
                'tags' => ['service', 'kommentar'],
            ],
            [
                'title' => 'Weiterempfehlung',
                'question_text' => 'Würden Sie diese Versicherung basierend auf Ihrer Erfahrung weiterempfehlen?',
                'type' => 'rating',
                'is_required' => true,
                'meta' => ['scale' => 5],
                'help_text' => '1 = auf keinen Fall, 5 = auf jeden Fall',
                'default_value' => 3,
                'is_active' => true,
                'frontend_title' => 'Weiterempfehlung',
                'frontend_description' => 'Wie wahrscheinlich ist es, dass Sie diese Versicherung weiterempfehlen?',
                'weight' => 7,
                'input_constraints' => ['min' => 1, 'max' => 5],
                'read_only' => false,
                'tags' => ['weiterempfehlung', 'zufriedenheit'],
            ],
            
        ];
        foreach ($questions as $question) {
            RatingQuestion::create($question);
        }
    }
}
