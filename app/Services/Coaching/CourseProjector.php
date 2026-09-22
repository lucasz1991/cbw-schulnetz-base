<?php

namespace App\Services\Coaching;

use App\Models\{CoachingContract, CoachingPlan, Course, CourseDay, CourseParticipantEnrollment};

class CourseProjector
{
    /** Called under the contract lock, only after UVS acknowledged this exact confirmed version. */
    public function project(CoachingContract $contract, CoachingPlan $plan): Course
    {
        if (!$plan->confirmed_at || $plan->status !== 'confirmed' || $plan->coaching_contract_id !== $contract->id
            || $contract->confirmed_plan_id !== $plan->id || $plan->contract_version !== $contract->contract_version) {
            throw new \LogicException('Nur ein vollständig bestätigter, unveränderter Gesamtplan kann einen Baustein erzeugen.');
        }
        if ($contract->course_id) return $contract->course()->firstOrFail();
        $items = collect($plan->items);
        $course = Course::create([
            // Local route identity; never sent to UVS as a class identifier.
            'klassen_id' => 'ec-'.$contract->uuid, 'type' => 'coaching', 'vtz' => 'E',
            'institut_id' => $contract->institut_id, 'title' => $contract->title,
            'primary_tutor_person_id' => $contract->tutor_person_id, 'is_active' => true,
            'planned_start_date' => $items->min('date'), 'planned_end_date' => $items->max('date'),
            'settings' => ['coaching_contract_id' => $contract->id, 'plan_revision' => $plan->revision],
        ]);
        CourseParticipantEnrollment::create([
            'course_id' => $course->id, 'person_id' => $contract->participant_person_id,
            'teilnehmer_id' => $contract->teilnehmer_id, 'klassen_id' => $course->klassen_id,
            'vtz' => 'E', 'status' => 'active', 'is_active' => true,
            'source_snapshot' => ['coaching_contract_id' => $contract->id],
        ]);
        foreach ($items->groupBy('date') as $date => $sessions) {
            CourseDay::create([
                'course_id' => $course->id, 'date' => $date, 'type' => 'coaching',
                'start_time' => $sessions->min('start'), 'end_time' => $sessions->max('end'),
                'std' => $sessions->sum('minutes') / $contract->unit_minutes,
                'topic' => mb_substr($sessions->pluck('topic')->implode(' / '), 0, 255),
                'day_sessions' => $sessions->map(fn ($item) => ['id' => $item['id'], 'label' => $item['topic'],
                    'start' => $item['start'], 'end' => $item['end'], 'break' => 0,
                    'room' => $item['location'], 'topic' => $item['topic'], 'notes' => ''])->values()->all(),
            ]);
        }
        $contract->update(['course_id' => $course->id]);
        return $course;
    }
}
