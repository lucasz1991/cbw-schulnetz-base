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
use Illuminate\Support\Str;

class PersonApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public bool $withoutCooldown = false;

    public ?string $manualRequestId = null;

    public function __construct(public int $personPk, bool $withoutCooldown = false)
    {
        $this->personPk = $personPk;
        $this->withoutCooldown = $withoutCooldown;
        $this->manualRequestId = $withoutCooldown ? (string) Str::uuid() : null;
    }

    public function uniqueId(): string
    {
        $manualSuffix = $this->withoutCooldown
            ? ':manual:' . ($this->manualRequestId ?? 'legacy')
            : '';

        return 'person-api-update:' . (string) $this->personPk . $manualSuffix;
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

        app(PersonUvsSyncService::class)->sync(
            $person,
            withoutCooldown: $this->withoutCooldown,
        );
    }
}
