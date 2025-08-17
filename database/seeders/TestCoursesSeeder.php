<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;

class TestCoursesSeeder extends Seeder
{
    public function run(): void
    {
        // Beispiel-Tutoren (falls deine User mit role=tutor existieren sollen)
        $tutors = User::where('role', 'tutor')->pluck('id')->all();

        // Falls noch keine Tutoren angelegt sind -> einfach 1 als Platzhalter
        if (empty($tutors)) {
            $tutors = [1];
        }

        // Kurs-Titel
        $titles = [
            'Einführung in PHP',
            'Laravel für Fortgeschrittene',
            'JavaScript Grundlagen',
            'TailwindCSS im Einsatz',
            'Projektmanagement Basics',
            'SQL & Datenbanken',
            'Agile Methoden',
            'Cloud Grundlagen',
            'IT-Sicherheit',
            'Excel für Profis'
        ];

        foreach ($titles as $idx => $title) {
            $start = Carbon::now()->addDays($idx * 7)->setTime(9, 0);   // jede Woche ein neuer Kurs
            $end   = (clone $start)->addDays(5)->setTime(16, 0);       // Dauer: 5 Tage

            Course::firstOrCreate(
                ['title' => $title],
                [
                    'description'   => "Testbeschreibung für den Kurs '{$title}'.",
                    'start_time'    => $start,
                    'end_time'      => $end,
                    'tutor_id'      => $tutors[array_rand($tutors)],
                ]
            );
        }
    }
}
