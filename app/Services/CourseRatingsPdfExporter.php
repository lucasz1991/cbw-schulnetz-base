<?php

namespace App\Services;

use App\Models\Course;
use Barryvdh\DomPDF\Facade\Pdf;

class CourseRatingsPdfExporter
{
    /**
     * Erzeugt eine temporäre PDF-Datei mit den Baustein-Bewertungen.
     * Rückgabewert ist der Pfad zur temporären Datei oder null.
     */
    public function generate(Course $course): ?string
    {
        $course->loadMissing(['tutor', 'days']);

        /** @var \Illuminate\Support\Collection<int, \App\Models\CourseRating> $ratings */
        $ratings = $course->ratings()
            ->where(function ($query) {
                $query->where('skip_course_rating', false)
                    ->orWhereNull('skip_course_rating');
            })
            ->orderBy('created_at')
            ->get();

        if ($ratings->isEmpty()) {
            return null;
        }

        // Zeitraum / Termin-Label
        $days = $course->days()
            ->orderBy('date')
            ->get();

        $from = $course->planned_start_date
            ?? ($days->first()?->date ?? null);

        $to = $course->planned_end_date
            ?? ($days->last()?->date ?? null);

        $fromLabel = $from ? \Carbon\Carbon::parse($from)->format('d.m.Y') : '—';
        $toLabel   = $to   ? \Carbon\Carbon::parse($to)->format('d.m.Y')   : '—';

        $terminLabel = trim(($course->termin_id ?: '') . ' - ' . $fromLabel . ' bis ' . $toLabel);

        // Kleine Helper für Durchschnittswerte
        $avgField = function (string $field) use ($ratings): ?float {
            $values = $ratings
                ->pluck($field)
                ->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))
                ->map(fn ($v) => (float) $v);

            if ($values->isEmpty()) {
                return null;
            }

            return round($values->avg(), 2);
        };

        $avgCategory = function (array $fields) use ($avgField): ?float {
            $vals = collect($fields)
                ->map(fn ($f) => $avgField($f))
                ->filter(fn ($v) => $v !== null)
                ->values();

            if ($vals->isEmpty()) {
                return null;
            }

            return round($vals->avg(), 2);
        };

        // Sektionen im Stil deines PDFs
        $sections = [
            'kb' => [
                'label' => 'Kundenbetreuung',
                'avg'   => $avgCategory(['kb_1', 'kb_2', 'kb_3']),
                'questions' => [
                    [
                        'label' => 'Wie kompetent sind die Mitarbeiter/-innen der Kundenbetreuung?',
                        'avg'   => $avgField('kb_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('kb_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('kb_3'),
                    ],
                ],
            ],
            'sa' => [
                'label' => 'Systemadministration',
                'avg'   => $avgCategory(['sa_1', 'sa_2', 'sa_3']),
                'questions' => [
                    [
                        'label' => 'Wie kompetent sind die Mitarbeiter/-innen der Systemadministration?',
                        'avg'   => $avgField('sa_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('sa_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('sa_3'),
                    ],
                ],
            ],
            'il' => [
                'label' => 'Institutsleitung',
                'avg'   => $avgCategory(['il_1', 'il_2', 'il_3']),
                'questions' => [
                    [
                        'label' => 'Wie beurteilen Sie die Organisation im Institut?',
                        'avg'   => $avgField('il_1'),
                    ],
                    [
                        'label' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                        'avg'   => $avgField('il_2'),
                    ],
                    [
                        'label' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                        'avg'   => $avgField('il_3'),
                    ],
                ],
            ],
            'do' => [
                'label' => 'Dozent/-in',
                'avg'   => $avgCategory(['do_1', 'do_2', 'do_3']),
                'questions' => [
                    [
                        'label' => 'War der Dozent / die Dozentin Ihnen gegenüber freundlich und höflich?',
                        'avg'   => $avgField('do_1'),
                    ],
                    [
                        'label' => 'Wie beurteilen Sie die Fachkompetenz der/s Dozenten/-in?',
                        'avg'   => $avgField('do_2'),
                    ],
                    [
                        'label' => 'Wie beurteilen Sie ihre/seine methodischen und didaktischen Fähigkeiten?',
                        'avg'   => $avgField('do_3'),
                    ],
                ],
            ],
        ];

        // Meta-Infos für Kopfbereich
        $meta = [
            'class_label'   => $course->courseClassName,
            'module_label' => $course->courseShortName,
            'tutor_name'    => optional($course->tutor)->full_name
                ?? trim(($course->tutor->vorname ?? '').' '.($course->tutor->nachname ?? '')),
            'termin_label'  => $terminLabel,
            'ratings_count' => $ratings->count(),
        ];

        // View rendern
        $pdf = Pdf::loadView('pdf.courses.course-ratings', [
            'course'   => $course,
            'meta'     => $meta,
            'sections' => $sections,
            'ratings'  => $ratings,
        ])->setPaper('a4', 'portrait');

        $tmpPath = tempnam(sys_get_temp_dir(), 'rating_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Temporäre PDF-Datei konnte nicht erstellt werden.');
        }

        try {
            $pdf->save($tmpPath);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            throw $e;
        }

        return $tmpPath;
    }
}
