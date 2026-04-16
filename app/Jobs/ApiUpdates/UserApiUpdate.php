<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Person;
use App\Models\User;
use App\Services\ApiUvs\PersonUvsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UserApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

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

        $persons = $user->resolveUvsApiUpdatePersons($this->personPk, true);

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
}
