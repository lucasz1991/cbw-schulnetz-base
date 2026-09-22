<?php

namespace App\Services\Coaching;

use App\Models\{CoachingContract, CoachingOutbox, Message, Person, Setting, User};
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncService
{
    public function import(): int
    {
        if (!Access::available()) return 0;
        $after = 0;
        $count = 0;
        do {
            $response = app(ApiUvsService::class)->request('GET', '/api/coaching/contracts', [], ['after_id' => $after]);
            if (!($response['ok'] ?? false)) throw new RuntimeException('UVS-Abgleich fehlgeschlagen (HTTP '.($response['status'] ?? 0).').');
            $body = $response['data'] ?? [];
            if (!is_array($body['data'] ?? null)) throw new RuntimeException('Ungültige UVS-Antwort.');
            foreach ($body['data'] as $row) { $this->importContract($row); $count++; }
            $next = $body['next_after_id'] ?? null;
            if ($next !== null && (int)$next <= $after) throw new RuntimeException('Ungültige UVS-Seitennummer.');
            $after = (int)$next;
        } while ($next !== null);
        return $count;
    }

    public function importContract(array $row): void
    {
        validator($row, [
            'id' => 'required|integer|min:1', 'institut_id' => 'required|integer|min:1',
            'person_id' => 'required|string|max:255', 'beratung_id' => 'required|string|max:255',
            'tutor_person_id' => 'nullable|string|max:13',
            'title' => 'required|string|max:255', 'agreed_minutes' => 'required|integer|min:0|max:180000',
            'unit_minutes' => 'required|integer|min:1|max:720', 'version' => 'required|string|size:64',
            'contacts' => 'sometimes|array', 'contacts.*.person_id' => 'nullable|string|max:255', 'contacts.*.email' => 'nullable|email|max:255',
            'planning_fingerprint' => 'nullable|string|size:64', 'plan_hash' => 'nullable|string|size:64',
            'status' => 'required|in:active,inactive,draft', 'valid_from' => 'nullable|date_format:Y-m-d',
            'valid_until' => 'nullable|date_format:Y-m-d', 'cancelled_on' => 'nullable|date_format:Y-m-d',
        ])->validate();
        DB::transaction(function () use ($row) {
            $contract = CoachingContract::where('uvs_contract_id', $row['id'])->lockForUpdate()->first();
            if (!$contract && $row['status'] === 'inactive') return;
            if ($contract && ($contract->uvs_person_id !== $row['person_id'] || $contract->institut_id != $row['institut_id'])) throw new RuntimeException('UVS-Vertragsidentität widerspricht bestehender Zuordnung.');
            $person = Person::where('person_id', $row['person_id'])->where('institut_id', $row['institut_id'])->first();
            $sourceTutor = $row['tutor_person_id'] ?? null;
            $tutor = $sourceTutor ? Person::where('person_id', $sourceTutor)->where('institut_id', $row['institut_id'])->where('role', 'tutor')->first() : null;
            if ($sourceTutor === $row['person_id'] || ($tutor?->user_id && $tutor->user_id === $person?->user_id)) throw new RuntimeException('Dozent und Teilnehmer müssen unterschiedliche Personen und Konten sein.');
            $isNew = !$contract;
            $sourceChanged = $contract && $contract->uvs_tutor_person_id !== $sourceTutor;
            $wasPlanning = $contract?->planningAllowed();
            $assignmentChanged = $contract && ($contract->uvs_tutor_person_id !== $sourceTutor || $contract->tutor_person_id !== $tutor?->id);
            if ($assignmentChanged && $contract->confirmed_plan_id) throw new RuntimeException('Die UVS-Dozentenzuordnung widerspricht dem bestätigten Gesamtplan.');
            if ($assignmentChanged && $contract->uvs_tutor_person_id !== $sourceTutor) {
                app(NoticeService::class)->record($contract, 'assignment_removed', $row['version'], ['tutor']);
            }
            $values = ['notification_contacts' => $row['contacts'] ?? ($contract?->notification_contacts ?? []),
                'planning_fingerprint' => $row['planning_fingerprint'] ?? null, 'uvs_tutor_person_id' => $sourceTutor, 'tutor_person_id' => $tutor?->id,
                'uvs_contract_id' => $row['id'], 'institut_id' => $row['institut_id'], 'uvs_person_id' => $row['person_id'],
                'beratung_id' => $row['beratung_id'], 'teilnehmer_id' => $row['teilnehmer_id'] ?? null,
                'massnahme_id' => $row['massnahme_id'] ?? null, 'participant_person_id' => $person?->id,
                'title' => $row['title'], 'agreed_minutes' => $row['agreed_minutes'], 'unit_minutes' => $row['unit_minutes'],
                'contract_version' => $row['version'], 'contract_status' => $row['status'],
                'valid_from' => $row['valid_from'] ?? null, 'valid_until' => $row['valid_until'] ?? null,
                'cancelled_on' => $row['cancelled_on'] ?? null, 'last_imported_at' => now(),
            ];
            if ($assignmentChanged) $values += ['tutor_notified_user_id' => null, 'tutor_notified_at' => null];
            if (!$contract) $contract = CoachingContract::create($values + ['uuid' => (string)Str::uuid()]);
            else {
                // Activation changes lifecycle identity, not the jointly agreed teaching plan.
                // Rebind only with an unchanged scope fingerprint AND the exact UVS-acknowledged plan hash.
                $plan = $contract->confirmedPlan;
                if ($plan && $contract->contract_version !== $row['version']
                    && $contract->planning_fingerprint && $contract->planning_fingerprint === ($row['planning_fingerprint'] ?? null)
                    && $this->acknowledges($contract, $plan, $row)) {
                    $plan->update(['contract_version' => $row['version']]);
                    app(NoticeService::class)->record($contract, 'plan_transferred', (string)$plan->revision);
                    CoachingOutbox::where('coaching_contract_id', $contract->id)->whereNull('sent_at')->update(['sent_at' => now(), 'last_error' => null]);
                }
                if (($contract->contract_version !== $row['version'] || $assignmentChanged) && !$contract->confirmed_plan_id) {
                    $hadPlan = $contract->plans()->where('status', 'proposed')->exists();
                    $contract->plans()->where('status', 'proposed')->update(['status' => 'superseded']);
                    $values['revision'] = $contract->revision + 1;
                }
                $contract->update($values);
            }
            if ($contract->course_id) $contract->course()->update(['is_active' => $contract->activeOn()]);
            $notices = app(NoticeService::class);
            if ($contract->planningAllowed()) {
                if (!$contract->confirmed_plan_id && ($isNew || $sourceChanged)) $notices->record($contract, 'planning_requested', $row['version']);
                if (!empty($hadPlan)) $notices->record($contract, 'plan_changed', $row['version']);
                if ($wasPlanning === false) $notices->record($contract, 'resumed', $row['version']);
                $plan = $contract->fresh()->confirmedPlan;
                if ($plan && $plan->contract_version !== $contract->contract_version) $notices->record($contract, 'contract_review', $row['version']);
                if ($contract->activeOn() && $plan && $plan->contract_version === $contract->contract_version && $this->acknowledges($contract, $plan, $row)) {
                    app(CourseProjector::class)->project($contract, $plan);
                    $notices->record($contract, 'released', (string)$plan->revision);
                }
            } elseif ($wasPlanning) $notices->record($contract, 'stopped', $row['version']);
            // Registration can finish after import; wake pending inbox delivery for newly linked accounts.
            \App\Models\CoachingNotice::where('coaching_contract_id', $contract->id)->whereNull('message_id')->whereNull('dismissed_at')->update(['available_at' => now()]);
            \App\Jobs\DeliverCoachingNotices::dispatch($contract->id)->afterCommit();
        }, 3);
    }

    private function acknowledges(CoachingContract $contract, \App\Models\CoachingPlan $plan, array $row): bool
    {
        $hash = hash('sha256', json_encode([$contract->uvs_tutor_person_id, $plan->items], JSON_UNESCAPED_UNICODE));
        return (int)($row['plan_revision'] ?? 0) === (int)$plan->revision && !empty($row['plan_hash'])
            && hash_equals($hash, $row['plan_hash']);
    }

    public function sendPending(): int
    {
        if (!Access::available()) return 0;
        $sent = 0;
        foreach (CoachingOutbox::whereNull('sent_at')->where('available_at', '<=', now())->orderBy('id')->limit(100)->pluck('id') as $id) {
            // The command holds a cache lock. Idempotency also covers a process crash after the remote commit.
            $event = CoachingOutbox::findOrFail($id);
            $contract = $event->contract;
            if (!$contract->planningAllowed() || $contract->contract_version !== $event->payload['contract_version']) {
                $event->update(['last_error' => 'Vertrag geändert oder beendet; keine Freigabe.', 'available_at' => now()->addHour()]);
                continue;
            }
            $result = app(ApiUvsService::class)->request('PUT', '/api/coaching/contracts/'.$contract->uvs_contract_id.'/plan', $event->payload + ['event_id' => $event->event_id]);
            $event->increment('attempts');
            if (!($result['ok'] ?? false) || (int)data_get($result, 'data.revision') !== (int)$event->payload['revision']) {
                $event->update(['last_error' => 'UVS-Rückmeldung fehlgeschlagen (HTTP '.($result['status'] ?? 0).').',
                    'available_at' => now()->addMinutes(min(60, 2 ** min(6, $event->attempts)))]);
                continue;
            }
            DB::transaction(function () use ($event) {
                $contract = CoachingContract::lockForUpdate()->findOrFail($event->coaching_contract_id);
                $plan = $contract->confirmedPlan;
                if (!$plan || $plan->revision !== (int)$event->payload['revision'] || $plan->contract_version !== $contract->contract_version) throw new RuntimeException('Plan wurde während des Abgleichs geändert.');
                app(NoticeService::class)->record($contract, 'plan_transferred', (string)$plan->revision);
                if ($contract->activeOn()) {
                    app(CourseProjector::class)->project($contract, $plan);
                    app(NoticeService::class)->record($contract, 'released', (string)$plan->revision);
                }
                $event->update(['sent_at' => now(), 'last_error' => null]);
            }, 3);
            $sent++;
        }
        return $sent;
    }
}
