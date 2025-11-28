<?php

namespace App\Jobs;

use App\Models\Person;
use App\Jobs\ApiUpdates\PersonApiUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateApiDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    public function handle(): void
    {
        Log::info('UpdateApiDataJob: Starte nächtlichen Personen-Sync.');

        Person::whereNotNull('person_id')
            ->chunk(200, function ($persons) {
                foreach ($persons as $person) {
                    $person->apiupdate();
                }
            });

        Log::info('UpdateApiDataJob: Sync abgeschlossen.');
    }
}
