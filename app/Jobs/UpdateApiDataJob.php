<?php

namespace App\Jobs;

use App\Models\User;
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
        Log::info('UpdateApiDataJob: Starte nächtlichen User-Sync.');

        User::query()
            ->whereHas('persons', function ($query) {
                $query->whereNotNull('person_id');
            })
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $user->uvsApiUpdate();
                }
            });

        Log::info('UpdateApiDataJob: Sync abgeschlossen.');
    }
}
