<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Person;
use App\Models\User;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\PersonUvsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UserApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 900;

    /** @var array<int,int> */
    public array $backoff = [10, 60];

    public function __construct(
        public int $userPk,
        public ?int $personPk = null,
    ) {
    }

    public function uniqueId(): string
    {
        return 'user-api-update:' . $this->userPk . ':' . ($this->personPk !== null ? $this->personPk : 'all');
    }

    public function handle(): void
    {
        $user = User::find($this->userPk);

        if (! $user) {
            Log::warning("UserApiUpdate: User {$this->userPk} nicht gefunden.");
            return;
        }

        $newlyLinkedPersons = $this->syncUvsPersonsByEmail($user);
        $persons = $this->resolvePersonsForSync($user, $newlyLinkedPersons);

        if ($persons->isEmpty()) {
            Log::warning('UserApiUpdate: Keine Personen zum Updaten gefunden.', [
                'user_id' => $user->id,
                'person_id' => $this->personPk,
            ]);
            return;
        }

        $syncService = app(PersonUvsSyncService::class);
        $results = [];

        Person::withoutUserPortalRoleSync(function () use ($persons, $syncService, &$results) {
            foreach ($persons as $person) {
                try {
                    $results[] = $syncService->sync($person);
                } catch (\Throwable $e) {
                    Log::error('UserApiUpdate: Person-Sync fehlgeschlagen.', [
                        'user_id' => $person->user_id,
                        'person_pk' => $person->id,
                        'person_id' => $person->person_id,
                        'error' => $e->getMessage(),
                    ]);

                    $results[] = [
                        'ok' => false,
                        'person_pk' => $person->id,
                        'reason' => 'exception',
                    ];
                }
            }
        });

        $user->refresh()->syncPortalRoleFromPersons();

        Log::info('UserApiUpdate summary', [
            'user_id' => $user->id,
            'requested_person_id' => $this->personPk,
            'person_ids' => $persons->pluck('id')->all(),
            'successful' => collect($results)->where('ok', true)->count(),
            'failed' => collect($results)->where('ok', false)->count(),
        ]);
    }

    protected function resolvePersonsForSync(User $user, Collection $newlyLinkedPersons): Collection
    {
        $persons = $user->resolveUvsApiUpdatePersons($this->personPk, true);

        if ($this->personPk === null) {
            $persons = $user->resolveUvsApiUpdatePersons(null, true);
        }

        return $persons
            ->merge($newlyLinkedPersons)
            ->unique('id')
            ->values();
    }

    protected function syncUvsPersonsByEmail(User $user): Collection
    {
        $email = trim((string) $user->email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return collect();
        }

        try {
            $response = app(ApiUvsService::class)->getParticipantbyMail($email);
        } catch (\Throwable $e) {
            Log::error('UserApiUpdate: UVS-E-Mail-Abgleich fehlgeschlagen.', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        if (! ($response['ok'] ?? false)) {
            Log::warning('UserApiUpdate: UVS-E-Mail-Abgleich lieferte keinen Erfolg.', [
                'user_id' => $user->id,
                'email' => $email,
                'status' => $response['status'] ?? null,
                'message' => $response['message'] ?? null,
            ]);

            return collect();
        }

        $uvsPersons = $this->extractUvsPersonsFromResponse($response['data'] ?? null);
        if ($uvsPersons->isEmpty()) {
            return collect();
        }

        $linkedPersons = collect();

        Person::withoutUserPortalRoleSync(function () use ($user, $uvsPersons, &$linkedPersons) {
            foreach ($uvsPersons as $uvsPerson) {
                $uvsPersonId = trim((string) ($uvsPerson->person_id ?? ''));
                if ($uvsPersonId === '') {
                    continue;
                }

                $person = Person::withTrashed()
                    ->where('person_id', $uvsPersonId)
                    ->first();

                if ($person && ! empty($person->user_id) && (int) $person->user_id !== (int) $user->id) {
                    Log::warning('UserApiUpdate: UVS-Person bereits anderem User zugeordnet, Verknuepfung wird nicht ueberschrieben.', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'person_pk' => $person->id,
                        'uvs_person_id' => $uvsPersonId,
                        'linked_user_id' => $person->user_id,
                    ]);
                    continue;
                }

                $wasNew = ! $person;
                $wasUnlinked = $person && empty($person->user_id);
                $seedRole = $person?->role;

                if (! is_string($seedRole) || $seedRole === '') {
                    $seedRole = in_array($user->role, ['guest', 'tutor'], true) ? $user->role : 'guest';
                }

                $mapped = Person::mapFromUvsPayload($uvsPerson, $seedRole);

                if ($person) {
                    $person->fill($mapped);

                    if (empty($person->user_id)) {
                        $person->user_id = $user->id;
                    }

                    $person->saveQuietly();
                } else {
                    $person = new Person(array_merge($mapped, [
                        'user_id' => $user->id,
                    ]));
                    $person->saveQuietly();
                }

                if ($wasNew || $wasUnlinked) {
                    $linkedPersons->push($person);
                }
            }
        });

        if ($linkedPersons->isNotEmpty()) {
            Log::info('UserApiUpdate: Neue oder bislang unverknuepfte UVS-Personen ueber E-Mail importiert.', [
                'user_id' => $user->id,
                'email' => $email,
                'person_ids' => $linkedPersons->pluck('id')->all(),
                'uvs_person_ids' => $linkedPersons->pluck('person_id')->all(),
            ]);
        }

        return $linkedPersons
            ->unique('id')
            ->values();
    }

    protected function extractUvsPersonsFromResponse(mixed $data): Collection
    {
        $persons = collect();

        if (! is_array($data)) {
            return $persons;
        }

        if (! empty($data['person']) && is_array($data['person'])) {
            $persons->push((object) $data['person']);
        }

        if (! empty($data['persons']) && is_array($data['persons'])) {
            foreach ($data['persons'] as $person) {
                if (is_array($person)) {
                    $persons->push((object) $person);
                }
            }
        }

        return $persons
            ->filter(fn (object $person) => ! empty($person->person_id))
            ->unique('person_id')
            ->values();
    }
}
