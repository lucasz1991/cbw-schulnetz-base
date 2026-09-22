<?php

namespace App\Services\Coaching;

use App\Jobs\DeliverCoachingNotices;
use App\Models\{CoachingContract, CoachingNotice, Message, Person, Setting, User};
use App\Notifications\CoachingNotification;
use Illuminate\Support\Facades\{DB, Notification};

class NoticeService
{
    public function linkRegisteredUser(User $user): void
    {
        if (!Access::available()) return;
        foreach ($user->persons as $person) {
            DB::transaction(function () use ($person) {
                $contracts = CoachingContract::where('institut_id', $person->institut_id)
                    ->where(fn ($q) => $q->where('uvs_person_id', $person->person_id)->orWhere('uvs_tutor_person_id', $person->person_id))
                    ->lockForUpdate()->get();
                foreach ($contracts as $contract) {
                    if ($contract->uvs_person_id === $person->person_id && !$contract->participant_person_id) {
                        $contract->participant_person_id = $person->id;
                    }
                    if ($contract->uvs_tutor_person_id === $person->person_id && $person->role === 'tutor' && !$contract->tutor_person_id) {
                        $contract->tutor_person_id = $person->id;
                    }
                    $contract->save();
                    $contract->notices()->where('uvs_person_id', $person->person_id)->whereNull('message_id')->whereNull('dismissed_at')->update(['available_at' => now()]);
                    DeliverCoachingNotices::dispatch($contract->id)->afterCommit();
                }
            });
        }
    }

    public function record(CoachingContract $contract, string $kind, string $event, array $roles = ['participant', 'tutor']): void
    {
        foreach ($roles as $role) {
            $id = $role === 'tutor' ? $contract->uvs_tutor_person_id : $contract->uvs_person_id;
            if (!$id) continue;
            CoachingNotice::firstOrCreate(['event_key' => hash('sha256', $contract->id.'|'.$kind.'|'.$event.'|'.$role.'|'.$id)], [
                'coaching_contract_id' => $contract->id, 'kind' => $kind, 'recipient_role' => $role,
                'uvs_person_id' => $id, 'content' => $this->content($contract, $kind, $role) + ['contact' => $contract->notification_contacts[$role] ?? [], 'plan_revision' => $contract->revision], 'available_at' => now(),
            ]);
        }
        DeliverCoachingNotices::dispatch($contract->id)->afterCommit();
    }

    public function content(CoachingContract $contract, string $kind, string $role): array
    {
        $title = $contract->title;
        $units = $contract->agreed_minutes / $contract->unit_minutes;
        $subject = 'Einzelcoaching: '.$title;
        $lines = ["Ihr Einzelcoaching: {$title} (Vorgang {$contract->uvs_contract_id})."];
        $lines[] = match ($kind) {
            'planning_requested' => $role === 'tutor'
                ? "Sie wurden im UVS als Dozent ausgewählt. Bitte stimmen Sie mit dem Teilnehmer alle Termine für den vollständigen Umfang von {$units} UE im Schulnetz ab."
                : "Bitte stimmen Sie mit Ihrem im UVS zugeordneten Dozenten alle Termine für den vollständigen Umfang von {$units} UE im Schulnetz ab.",
            'plan_proposed' => 'Ein neuer Gesamtplan liegt zur Abstimmung vor. Bitte prüfen Sie alle Termine und bestätigen Sie den vollständigen Plan oder klären Sie Änderungswünsche im Chat.',
            'plan_confirmed' => 'Der Gesamtplan wurde von der anderen Seite bestätigt. Bitte prüfen Sie im Schulnetz, ob Ihre eigene Bestätigung noch aussteht.',
            'plan_complete' => 'Sie und Ihr Gegenüber haben den vollständigen Terminplan bestätigt. Der Plan wird nun an die CBW-Verwaltung im UVS übermittelt. Die Freigabe zur Durchführung steht noch aus.',
            'plan_transferred' => 'Der vollständig bestätigte Terminplan wurde erfolgreich an das UVS übermittelt. Ein Mitarbeiter kann jetzt den finalen Vertrag erstellen und freigeben. Über die Freigabe werden Sie gesondert informiert.',
            'released' => $role === 'tutor'
                ? 'Die CBW-Verwaltung hat den Vertrag freigegeben. Ihr Einzelcoaching-Baustein und alle bestätigten Termine stehen im Schulnetz bereit. Bitte führen Sie Anwesenheit, Unterrichtsdokumentation und die weiteren Bausteinaufgaben wie gewohnt im Schulnetz.'
                : 'Die CBW-Verwaltung hat Ihren Vertrag freigegeben. Ihr Einzelcoaching-Baustein mit allen bestätigten Terminen und Unterlagen steht im Schulnetz bereit. Dort führen Sie auch Ihr Berichtsheft.',
            'contract_review' => 'Der Vertrag wurde nach der gemeinsamen Planbestätigung geändert. Die bisherige Freigabe ist nicht mehr gültig. Bitte klären Sie den Vorgang mit der CBW-Verwaltung und warten Sie mit der Durchführung.',
            'plan_changed' => 'Der Vertragsumfang oder die Dozentenzuordnung wurde im UVS geändert. Ein bisheriger unbestätigter Plan ist nicht mehr gültig. Bitte stimmen Sie einen neuen vollständigen Terminplan ab.',
            'assignment_removed' => 'Die Dozentenzuordnung wurde im UVS geändert. Sie sind diesem Einzelcoaching nicht mehr als Dozent zugeordnet. Bitte führen Sie keine weiteren Planungsschritte für diesen Vorgang durch.',
            'stopped' => 'Die CBW-Verwaltung hat den Vorgang deaktiviert, storniert oder eine Kündigung gemeldet. Bitte prüfen Sie den aktuellen Stand mit der Verwaltung. Weitere Terminabstimmungen sind gesperrt.',
            'resumed' => 'Die CBW-Verwaltung hat den Vorgang wieder freigegeben. Bitte prüfen Sie im Schulnetz den aktuellen Termin- und Vertragsstand.',
            'chat_message' => 'Eine neue Nachricht zur Terminabstimmung liegt vor. Bitte öffnen Sie den Chat Ihres Einzelcoachings im Schulnetz.',
            default => throw new \InvalidArgumentException('Unbekanntes Coaching-Ereignis.'),
        };
        if ($kind === 'planning_requested') {
            $subject = 'Einzelcoaching: vollständigen Terminplan abstimmen';
            $lines[] = 'Beide Seiten müssen alle Termine vor dem ersten Coaching bestätigen. Danach wird der Plan zur finalen Vertragserstellung an das UVS übermittelt. Bitte warten Sie mit der Durchführung auf die gesonderte Freigabe.';
        }
        if ($kind === 'released') {
            $subject = 'Ihr Einzelcoaching ist freigegeben';
            $first = $contract->confirmedPlan?->items[0] ?? null;
            if ($first) $lines[] = 'Erster Termin: '.\Carbon\Carbon::parse($first['date'])->format('d.m.Y.').', '.$first['start'].'–'.$first['end'].' Uhr. Ort / Teilnahme: '.$first['location'].'.';
        }
        return compact('subject', 'lines');
    }

    public function deliverPending(?int $contractId = null): int
    {
        if (!Access::available()) return 0;
        $ids = CoachingNotice::whereNull('dismissed_at')->where('available_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('mail_sent_at')->orWhereNull('message_id'))
            ->when($contractId, fn ($q) => $q->where('coaching_contract_id', $contractId))->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) $this->deliver($id);
        return $ids->count();
    }

    private function deliver(int $id): void
    {
        // The DB row lock also coordinates Admin and Base even when their cache stores differ.
        DB::transaction(function () use ($id) {
            $notice = CoachingNotice::lockForUpdate()->findOrFail($id);
            if ($notice->dismissed_at || ($notice->mail_sent_at && $notice->message_id) || $notice->available_at?->isFuture()) return;
            $contract = $notice->contract;
            if (in_array($notice->kind, ['plan_proposed', 'plan_confirmed'], true)
                && ((int)($notice->content['plan_revision'] ?? 0) !== $contract->revision || $contract->confirmed_plan_id)) {
                $notice->update(['dismissed_at' => now()]); return;
            }
            $currentId = $notice->recipient_role === 'tutor' ? $contract->uvs_tutor_person_id : $contract->uvs_person_id;
            if ($notice->kind !== 'assignment_removed' && ($notice->uvs_person_id !== $currentId
                || (!in_array($notice->kind, ['stopped'], true) && !$contract->planningAllowed()))) {
                $notice->update(['dismissed_at' => now()]); return;
            }
            $person = Person::where('person_id', $notice->uvs_person_id)->where('institut_id', $contract->institut_id)->first();
            $user = ($notice->recipient_role !== 'tutor' || $person?->role === 'tutor') ? $person?->user : null;
            $contact = $notice->kind === 'assignment_removed' ? ($notice->content['contact'] ?? []) : ($contract->notification_contacts[$notice->recipient_role] ?? []);
            $email = $user?->email ?: (($contact['person_id'] ?? null) === $notice->uvs_person_id ? ($contact['email'] ?? '') : '');
            $base = Setting::getValueUncached('api', 'base_api_url');
            $url = is_string($base) && preg_match('~^https?://~i', $base) ? rtrim($base, '/') : null;
            $errors = [];
            $addresses = array_map(fn ($c) => strtolower(trim((string)($c['email'] ?? ''))), $contract->notification_contacts ?? []);
            if (!$user && !empty($addresses['participant']) && ($addresses['participant'] === ($addresses['tutor'] ?? null))) {
                $notice->update(['last_error' => 'Dozent und Teilnehmer benötigen unterschiedliche E-Mail-Adressen.', 'available_at' => now()->addHour()]);
                return;
            }
            if (!$url) $errors[] = 'Schulnetz-Base-URL fehlt.';
            if ($user && !$notice->message_id && $url) {
                if (!User::whereKey(1)->exists()) $errors[] = 'Systemabsender fehlt.';
                else {
                    $body = implode('', array_map(fn ($line) => '<p>'.e($line).'</p>', $notice->content['lines']));
                    if ($notice->kind !== 'assignment_removed') $body .= '<p><a href="'.e($url.'/coaching?contract='.$contract->id).'">Einzelcoaching öffnen</a></p>';
                    $message = Message::create(['from_user' => 1, 'to_user' => $user->id, 'status' => 1,
                        'subject' => $notice->content['subject'], 'message' => $body]);
                    $notice->message_id = $message->id;
                    if ($notice->kind === 'planning_requested' && $notice->recipient_role === 'tutor') {
                        $contract->update(['tutor_notified_user_id' => $user->id, 'tutor_notified_at' => now()]);
                    }
                }
            }
            if (!$notice->mail_sent_at && $url) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Gültige Empfänger-E-Mail fehlt.';
                else try {
                    $informOnly = $notice->kind === 'assignment_removed' || ($notice->kind === 'stopped' && !$user);
                    Notification::route('mail', $email)->notify(new CoachingNotification($notice->content,
                        $informOnly ? null : $url.($user ? '/coaching?contract='.$contract->id : '/register'), !$user && !$informOnly));
                    $notice->mail_sent_at = now();
                } catch (\Throwable $e) { $errors[] = 'E-Mail-Versand fehlgeschlagen; erneuter Versuch folgt.'; }
            }
            if (!$user) $errors[] = 'Registrierung / Kontoverknüpfung ausstehend.';
            $notice->attempts++;
            $notice->last_error = $errors ? implode(' ', $errors) : null;
            $notice->available_at = now()->addMinutes(min(60, 2 ** min(6, $notice->attempts)));
            $notice->save();
        });
    }
}
