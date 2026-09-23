<?php

namespace App\Support;

use App\Models\{CoachingContract, User};
use App\Services\Coaching\Access;
use Carbon\CarbonImmutable;

/** Read-only participant projection; draft proposals never appear as appointments. */
class CoachingParticipantDashboard
{
    public function build(User $user): array
    {
        $dashboard = [
            'contracts' => [], 'total_units' => 0, 'confirmed_count' => 0, 'upcoming_count' => 0,
            'planning_count' => 0, 'ready_count' => 0, 'waiting_count' => 0,
            'next_session' => null, 'upcoming_sessions' => [],
        ];
        if (!Access::available()) return $dashboard;

        // Query the relation afresh; a loaded or client-selected person must not widen this scope.
        $contracts = CoachingContract::query()
            ->whereIn('participant_person_id', $user->persons()->select('persons.id'))
            ->with(['course', 'tutor.user', 'confirmedPlan', 'latestPlan'])
            ->orderByDesc('id')->get();
        $now = now('Europe/Berlin')->toImmutable();
        $sessions = [];

        foreach ($contracts as $contract) {
            $planningUrl = route('coaching.planning', ['contract' => $contract->id]);
            $courseUrl = $contract->course
                ? route('user.program.course.show', ['klassenId' => $contract->course->klassen_id]) : null;
            $tutorName = trim(($contract->tutor?->vorname ?? '').' '.($contract->tutor?->nachname ?? ''));
            $tutorName = $tutorName ?: ($contract->tutor?->user?->name ?: 'Dozent wird zugeordnet');
            $status = $this->status($contract);
            $units = $contract->unit_minutes > 0 ? $contract->agreed_minutes / $contract->unit_minutes : 0;
            $confirmedItems = $status !== 'ended' && $contract->hasCurrentConfirmedPlan()
                ? (array)$contract->confirmedPlan->items : [];
            $upcoming = [];

            if ($status === 'ready') {
                foreach ($confirmedItems as $item) {
                    $session = $this->session($contract, $item, $now);
                    if (!$session) continue;
                    $upcoming[] = $session + ['title' => $contract->title, 'tutor_name' => $tutorName,
                        'course_url' => $courseUrl, 'planning_url' => $planningUrl];
                }
            }

            $label = $contract->planning_label;
            if ($status === 'waiting' && !$contract->confirmed_plan_id && $contract->tutor) {
                $label = 'Deine Bestätigung liegt vor – Rückmeldung des Dozenten ausstehend';
            } elseif ($status === 'review' && $contract->hasCurrentConfirmedPlan()) {
                $label = 'Freigabe des Bausteins wird geprüft';
            }
            $actionLabel = match ($status) {
                'ready' => 'Baustein öffnen',
                'planning' => 'Termine abstimmen',
                'ended' => $courseUrl ? 'Baustein ansehen' : 'Terminplan ansehen',
                default => 'Terminplan ansehen',
            };
            $dashboard['contracts'][] = [
                'id' => $contract->id, 'title' => $contract->title, 'planning_label' => $label,
                'tutor_name' => $tutorName, 'status' => $status, 'units' => $units,
                'unit_minutes' => $contract->unit_minutes, 'confirmed_count' => count($confirmedItems),
                'upcoming_count' => count($upcoming), 'planning_url' => $planningUrl, 'course_url' => $courseUrl,
                'action_label' => $actionLabel,
                'action_url' => $status === 'ready' || ($status === 'ended' && $courseUrl) ? $courseUrl : $planningUrl,
            ];
            if ($status !== 'ended') $dashboard['total_units'] += $units;
            $dashboard['confirmed_count'] += count($confirmedItems);
            $dashboard['upcoming_count'] += count($upcoming);
            if (in_array($status, ['planning', 'ready', 'waiting'], true)) $dashboard[$status.'_count']++;
            array_push($sessions, ...$upcoming);
        }

        usort($sessions, fn ($a, $b) => [$a['date'], $a['start'], $a['contract_id'], $a['id']]
            <=> [$b['date'], $b['start'], $b['contract_id'], $b['id']]);
        $dashboard['upcoming_sessions'] = array_slice($sessions, 0, 3);
        $dashboard['next_session'] = $sessions[0] ?? null;

        return $dashboard;
    }

    private function status(CoachingContract $contract): string
    {
        if (!$contract->planningAllowed()) return 'ended';
        if ($contract->confirmed_plan_id && (!$contract->hasCurrentConfirmedPlan() || !$contract->tutor)) return 'review';
        if ($contract->startReady()) return $contract->course?->is_active ? 'ready' : 'review';
        if ($contract->confirmed_plan_id || !$contract->tutor_person_id || !$contract->tutor) return 'waiting';

        $plan = $contract->latestPlan;
        if ($plan && $plan->status === 'proposed' && $plan->contract_version === $contract->contract_version && $plan->revision === $contract->revision
            && $plan->participant_person_id === $contract->participant_person_id
            && $plan->tutor_person_id === $contract->tutor_person_id && $plan->participant_confirmed_at) return 'waiting';

        return 'planning';
    }

    private function session(CoachingContract $contract, mixed $item, CarbonImmutable $now): ?array
    {
        if (!is_array($item) || !isset($item['date'], $item['start'], $item['end'])
            || !in_array($item['format'] ?? null, ['online', 'presence'], true)) return null;
        $startText = $item['date'].' '.$item['start'];
        $endText = $item['date'].' '.$item['end'];
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $startText, 'Europe/Berlin');
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $endText, 'Europe/Berlin');
        } catch (\Throwable) {
            return null;
        }
        if (!$start || !$end || $start->format('Y-m-d H:i') !== $startText || $end->format('Y-m-d H:i') !== $endText
            || $end->lte($now) || $end->lte($start)
            || ($contract->valid_from && $item['date'] < $contract->valid_from->toDateString())
            || ($contract->valid_until && $item['date'] > $contract->valid_until->toDateString())) return null;

        return [
            'id' => $item['id'] ?? $startText, 'contract_id' => $contract->id,
            'date' => $item['date'], 'date_label' => $start->format('d.m.Y'),
            'weekday' => $start->locale('de')->isoFormat('dddd'), 'start' => $item['start'], 'end' => $item['end'],
            'location' => $item['location'] ?? '', 'mode' => ($item['format'] ?? '') === 'presence' ? 'onsite' : 'online',
            'topic' => $item['topic'] ?? $contract->title, 'minutes' => $start->diffInMinutes($end),
        ];
    }
}
