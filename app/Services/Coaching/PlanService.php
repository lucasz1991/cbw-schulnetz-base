<?php

namespace App\Services\Coaching;

use App\Models\{CoachingContract, CoachingOutbox, CoachingPlan, CourseDay, Message, Person, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function actor(CoachingContract $contract, User $user): string
    {
        abort_unless(Access::available(), 404);
        $ids = $user->persons()->pluck('persons.id')->all();
        $participant = in_array($contract->participant_person_id, $ids);
        $tutor = in_array($contract->tutor_person_id, $ids);
        // Two different people/accounts must consent; an administrator cannot consent for either.
        abort_unless($participant xor $tutor, 403);
        return $participant ? 'participant' : 'tutor';
    }

    public function propose(int $id, User $user, int $expectedRevision, array $items): CoachingPlan
    {
        return DB::transaction(function () use ($id, $user, $expectedRevision, $items) {
            $contract = CoachingContract::lockForUpdate()->findOrFail($id);
            $this->actor($contract, $user);
            $this->editable($contract, $expectedRevision);
            $items = app(PlanValidator::class)->validate($items, $contract->agreed_minutes,
                $contract->valid_from?->toDateString(), $contract->valid_until?->toDateString());
            if ($items[0]['starts_at'] <= now('UTC')->format('Y-m-d H:i:s')) {
                $this->fail('Alle Termine müssen in der Zukunft liegen.');
            }
            $contract->plans()->where('status', 'proposed')->update(['status' => 'superseded']);
            $contract->increment('revision');
            $plan = $contract->plans()->create([
                'revision' => $contract->revision, 'contract_version' => $contract->contract_version,
                'participant_person_id' => $contract->participant_person_id,
                'tutor_person_id' => $contract->tutor_person_id, 'created_by' => $user->id, 'items' => $items,
            ]);
            $this->notifyOther($contract, $user, 'plan_proposed', (string)$plan->revision);
            return $plan;
        }, 3);
    }

    public function confirm(int $id, User $user, int $revision): void
    {
        DB::transaction(function () use ($id, $user, $revision) {
            $contract = CoachingContract::lockForUpdate()->findOrFail($id);
            $actor = $this->actor($contract, $user);
            $this->editable($contract, $revision);
            $plan = $contract->plans()->where('revision', $revision)->lockForUpdate()->firstOrFail();
            if ($plan->contract_version !== $contract->contract_version || $plan->status !== 'proposed') {
                $this->fail('Der Plan ist nicht mehr aktuell. Bitte neu laden.');
            }
            // Serialize bookings involving either person, including across different coaching contracts.
            Person::whereIn('id', [$contract->tutor_person_id, $contract->participant_person_id])
                ->orderBy('id')->lockForUpdate()->get();
            $items = app(PlanValidator::class)->validate($plan->items, $contract->agreed_minutes,
                $contract->valid_from?->toDateString(), $contract->valid_until?->toDateString());
            if ($items[0]['starts_at'] <= now('UTC')->format('Y-m-d H:i:s')) $this->fail('Der erste Termin liegt bereits in der Vergangenheit. Bitte den Gesamtplan neu abstimmen.');
            $this->checkConflicts($contract, $items);
            if ($plan->{$actor.'_confirmed_at'}) return;
            $plan->{$actor.'_confirmed_at'} = now();
            if ($plan->participant_confirmed_at && $plan->tutor_confirmed_at) {
                $plan->status = 'confirmed';
                $plan->confirmed_at = now();
                $contract->update(['confirmed_plan_id' => $plan->id]);
                CoachingOutbox::create([
                    'event_id' => (string) Str::uuid(), 'coaching_contract_id' => $contract->id,
                    'type' => 'plan.confirmed', 'available_at' => now(),
                    'payload' => ['revision' => $plan->revision, 'contract_version' => $plan->contract_version,
                        'tutor_person_id' => $contract->tutor->person_id, 'items' => $items],
                ]);
            }
            $plan->save();
            if ($plan->confirmed_at) app(NoticeService::class)->record($contract, 'plan_complete', (string)$plan->revision);
            else $this->notifyOther($contract, $user, 'plan_confirmed', $plan->revision.'-'.$actor);
        }, 3);
    }

    private function editable(CoachingContract $contract, int $revision): void
    {
        if (! $contract->planningAllowed() || ! $contract->tutor_person_id || ! $contract->participant_person_id) $this->fail('Ein freigegebener Planungsvorgang und beide Zuordnungen sind erforderlich.');
        if ($contract->cancelled_on) $this->fail('Für einen gekündigten Vertrag kann kein neuer Gesamtplan bestätigt werden.');
        if ($contract->revision !== $revision) $this->fail('Der Gesamtplan wurde inzwischen geändert. Bitte neu laden.');
        if ($contract->confirmed_plan_id) $this->fail('Der vollständig bestätigte Gesamtplan ist verbindlich und kann hier nicht mehr geändert werden.');
        if (! $contract->last_imported_at || $contract->last_imported_at->lt(now()->subMinutes(15))) $this->fail('Die Vertragsdaten müssen zuerst mit UVS abgeglichen werden. Bitte später erneut versuchen oder die Verwaltung informieren.');
        if ($contract->participant?->user_id && $contract->participant->user_id === $contract->tutor?->user_id) $this->fail('Dozent und Teilnehmer müssen unterschiedliche Konten verwenden.');
    }

    private function checkConflicts(CoachingContract $contract, array $items): void
    {
        $persons = [$contract->participant_person_id, $contract->tutor_person_id];
        foreach ($items as $item) {
            $days = CourseDay::with('course')->whereDate('date', $item['date'])->whereHas('course', function ($q) use ($persons, $item, $contract) {
                $q->where('is_active', true)->where(function ($q) use ($persons, $item, $contract) {
                    $q->whereIn('primary_tutor_person_id', $persons)
                        ->orWhereHas('enrollments', fn ($q) => $q->whereIn('person_id', $persons)->where('is_active', true));
                    if ($item['format'] === 'presence') $q->orWhere('institut_id', $contract->institut_id);
                });
            })->get();
            foreach ($days as $day) {
                $sharedPerson = in_array($day->course->primary_tutor_person_id, $persons)
                    || $day->course->enrollments()->whereIn('person_id', $persons)->where('is_active', true)->exists();
                $sessions = $day->day_sessions ?: [['start' => $day->start_time?->format('H:i'), 'end' => $day->end_time?->format('H:i')]];
                foreach ($sessions as $session) {
                    $sameRoom = $item['format'] === 'presence'
                        && $this->roomKey($item['location']) === $this->roomKey($session['room'] ?? $day->course->room ?? '');
                    if (!$sharedPerson && !$sameRoom) continue;
                    $start = substr((string) ($session['start'] ?? ''), 0, 5);
                    $end = substr((string) ($session['end'] ?? ''), 0, 5);
                    if (! $start || ! $end || ($item['start'] < $end && $item['end'] > $start)) $this->fail('Dozent, Teilnehmer oder Raum ist am '.$item['date'].' bereits für einen überschneidenden Unterrichtstermin gebucht.');
                }
            }
            $plans = CoachingPlan::where('status', 'confirmed')->where('coaching_contract_id', '<>', $contract->id)
                ->where(function ($q) use ($persons, $item, $contract) {
                    $q->whereIn('tutor_person_id', $persons)->orWhereIn('participant_person_id', $persons);
                    if ($item['format'] === 'presence') $q->orWhereHas('contract', fn ($q) => $q->where('institut_id', $contract->institut_id));
                })
                // The jointly confirmed draft already reserves these appointments while UVS prepares the final contract.
                ->whereHas('contract', fn ($q) => $q->whereIn('contract_status', ['draft', 'active']))->lockForUpdate()->get();
            foreach ($plans as $plan) foreach ($plan->items as $other) {
                if ($plan->contract->cancelled_on && $other['date'] > $plan->contract->cancelled_on->toDateString()) continue;
                $sharedPerson = in_array($plan->tutor_person_id, $persons) || in_array($plan->participant_person_id, $persons);
                $sameRoom = $item['format'] === 'presence' && $other['format'] === 'presence' && $this->roomKey($item['location']) === $this->roomKey($other['location']);
                if (($sharedPerson || $sameRoom) && $item['starts_at'] < $other['ends_at'] && $item['ends_at'] > $other['starts_at']) $this->fail('Dozent, Teilnehmer oder Raum ist bereits in einem anderen Einzelcoaching gebucht.');
            }
        }
    }

    public function message(int $id, User $user, string $body): void
    {
        validator(['body' => $body], ['body' => 'required|string|max:4000'])->validate();
        $contract = CoachingContract::findOrFail($id);
        $this->actor($contract, $user);
        abort_unless($contract->planningAllowed(), 403);
        DB::transaction(function () use ($contract, $user, $body) {
            $chat = $contract->messages()->create(['user_id' => $user->id, 'plan_revision' => $contract->revision, 'body' => trim($body)]);
            $this->notifyOther($contract, $user, 'chat_message', (string)$chat->id);
        });
    }

    private function notifyOther(CoachingContract $contract, User $user, string $kind, string $event): void
    {
        $other = $this->actor($contract, $user) === 'tutor' ? 'participant' : 'tutor';
        app(NoticeService::class)->record($contract, $kind, $event, [$other]);
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['plan' => $message]); }

    private function roomKey(string $room): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $room))); }
}
