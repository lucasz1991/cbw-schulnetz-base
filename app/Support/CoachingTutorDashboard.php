<?php

namespace App\Support;

use App\Models\{CoachingContract, User};
use App\Services\Coaching\Access;
use Carbon\CarbonImmutable;

/** Read-only overview of the current tutor's assigned Einzelcoachings. */
class CoachingTutorDashboard
{
    public function build(User $user): array
    {
        $result = ['contracts' => [], 'open_count' => 0, 'ready_count' => 0, 'next_session' => null];
        if (!Access::available()) return $result;

        $contracts = CoachingContract::query()
            ->whereIn('tutor_person_id', $user->persons()->select('persons.id'))
            ->with(['participant.user', 'latestPlan', 'confirmedPlan', 'course'])
            ->orderByDesc('id')->get();
        $now = now('Europe/Berlin')->toImmutable();
        $sessions = [];

        foreach ($contracts as $contract) {
            $plan = $contract->latestPlan;
            $ready = $contract->startReady() && (bool)$contract->course?->is_active;
            $open = $contract->planningAllowed() && !$contract->confirmed_plan_id
                && (!$plan || $plan->status !== 'proposed' || !$plan->tutor_confirmed_at);
            $participant = trim(($contract->participant?->vorname ?? '').' '.($contract->participant?->nachname ?? ''));
            $result['contracts'][] = [
                'id' => $contract->id,
                'title' => $contract->title,
                'participant' => $participant ?: ($contract->participant?->user?->name ?: 'Teilnehmer noch nicht verknüpft'),
                'label' => $contract->planning_label,
                'units' => $contract->unit_minutes > 0 ? $contract->agreed_minutes / $contract->unit_minutes : 0,
                'open' => $open,
                'ready' => $ready,
                'url' => route('coaching.planning', ['contract' => $contract->id]),
                'course_url' => $ready ? route('tutor.courses.show', ['courseId' => $contract->course_id]) : null,
            ];
            if ($open) $result['open_count']++;
            if ($ready) $result['ready_count']++;

            if (!$ready || !$contract->hasCurrentConfirmedPlan()) continue;
            foreach ((array)$contract->confirmedPlan->items as $item) {
                if (!is_array($item) || !isset($item['date'], $item['start'], $item['end'])
                    || ($contract->valid_from && $item['date'] < $contract->valid_from->toDateString())
                    || ($contract->valid_until && $item['date'] > $contract->valid_until->toDateString())) continue;
                try {
                    $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $item['date'].' '.$item['start'], 'Europe/Berlin');
                    $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $item['date'].' '.$item['end'], 'Europe/Berlin');
                } catch (\Throwable) {
                    continue;
                }
                if (!$start || !$end || $start->format('Y-m-d H:i') !== $item['date'].' '.$item['start']
                    || $end->format('Y-m-d H:i') !== $item['date'].' '.$item['end'] || $end->lte($now) || $end->lte($start)) continue;
                $sessions[] = ['date' => $item['date'], 'date_label' => $start->locale('de')->isoFormat('ddd, DD. MMM'),
                    'start' => $item['start'], 'end' => $item['end'], 'topic' => $item['topic'] ?: $contract->title,
                    'participant' => $participant, 'url' => route('tutor.courses.show', ['courseId' => $contract->course_id])];
            }
        }

        usort($sessions, fn ($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);
        $result['next_session'] = $sessions[0] ?? null;
        return $result;
    }
}
