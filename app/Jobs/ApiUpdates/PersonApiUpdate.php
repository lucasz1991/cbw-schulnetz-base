<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Person;
use App\Services\ApiUvs\PersonUvsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PersonApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $personPk)
    {
        $this->personPk = $personPk;
    }

    public function uniqueId(): string
    {
        return 'person-api-update:' . (string) $this->personPk;
    }

    public function handle(): void
    {
        $person = Person::withTrashed()->find($this->personPk);

        if (! $person) {
            if (config('api_sync.debug_logs', false)) {
                Log::warning("PersonApiUpdate: Person {$this->personPk} nicht gefunden.");
            }
            return;
        }

        if (empty($person->person_id)) {
            if (config('api_sync.debug_logs', false)) {
                Log::warning("PersonApiUpdate: 'person_id' leer fuer persons.id={$person->id}.");
            }
            return;
        }

        app(PersonUvsSyncService::class)->sync($person);
    }
}
