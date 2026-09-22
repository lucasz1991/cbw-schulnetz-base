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
            $assignmentChanged = $contract && ($contract->uvs_tutor_person_id !== $sourceTutor || $contract->tutor_person_id !== $tutor?->id);
            if ($assignmentChanged && $contract->confirmed_plan_id) throw new RuntimeException('Die UVS-Dozentenzuordnung widerspricht dem bestätigten Gesamtplan.');
            $values = ['uvs_tutor_person_id' => $sourceTutor, 'tutor_person_id' => $tutor?->id,
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
                if (($contract->contract_version !== $row['version'] || $assignmentChanged) && !$contract->confirmed_plan_id) {
                    $contract->plans()->where('status', 'proposed')->update(['status' => 'superseded']);
                    $values['revision'] = $contract->revision + 1;
                }
                $contract->update($values);
            }
            if ($contract->course_id) $contract->course()->update(['is_active' => $contract->activeOn()]);
            $this->notifyAssignedTutor($contract, $tutor);
        }, 3);
    }

    /** Inbox write and delivery marker share the locked import transaction: retries cannot duplicate it. */
    private function notifyAssignedTutor(CoachingContract $contract, ?Person $tutor): void
    {
        $recipient = $tutor?->user;
        if (!$contract->activeOn() || $contract->confirmed_plan_id || !$recipient
            || ($contract->tutor_notified_at && $contract->tutor_notified_user_id === $recipient->id)) return;
        // Schulnetz's existing system-message convention uses user 1. Retry next import if not provisioned yet.
        if (!User::whereKey(1)->exists()) return;
        $baseUrl = Setting::getValue('api', 'base_api_url');
        $planningUrl = is_string($baseUrl) && preg_match('~^https?://~i', $baseUrl)
            ? rtrim($baseUrl, '/').'/coaching'
            : (\Illuminate\Support\Facades\Route::has('coaching.planning') ? route('coaching.planning') : '/coaching');
        Message::create([
            'from_user' => 1, 'to_user' => $recipient->id, 'status' => '1',
            'subject' => 'Einzelcoaching: vollständigen Terminplan abstimmen',
            'message' => '<p>Sie wurden im UVS für <strong>'.e($contract->title).'</strong> (Vertrag '.(int)$contract->uvs_contract_id.') als Dozent ausgewählt.</p>'
                .'<p>Bitte stimmen Sie im Schulnetz mit dem Teilnehmer alle Termine für den gesamten vereinbarten Umfang von '.e((string)($contract->agreed_minutes / $contract->unit_minutes)).' UE ab. Beide Seiten müssen den vollständigen Plan vor Beginn bestätigen.</p>'
                .'<p><a href="'.e($planningUrl.'?contract='.(int)$contract->id).'">Terminplanung öffnen</a></p>'
                .'<p>Anschließend erfolgen Durchführung, Dokumentation und Berichtsheft im gewohnten Schulnetz-Baustein.</p>',
        ]);
        $contract->update(['tutor_notified_user_id' => $recipient->id, 'tutor_notified_at' => now()]);
    }

    public function sendPending(): int
    {
        $sent = 0;
        foreach (CoachingOutbox::whereNull('sent_at')->where('available_at', '<=', now())->orderBy('id')->limit(100)->pluck('id') as $id) {
            // The command holds a cache lock. Idempotency also covers a process crash after the remote commit.
            $event = CoachingOutbox::findOrFail($id);
            $contract = $event->contract;
            if (!$contract->activeOn() || $contract->contract_version !== $event->payload['contract_version']) {
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
                app(CourseProjector::class)->project($contract, $plan);
                $event->update(['sent_at' => now(), 'last_error' => null]);
            }, 3);
            $sent++;
        }
        return $sent;
    }
}
