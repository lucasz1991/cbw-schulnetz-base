<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UpdateApiDataJob;

class UpdateApiDataCommand extends Command
{
    protected $signature = 'uvs:update-persons';
    protected $description = 'Aktualisiert alle Personendaten via UVS API';

    public function handle()
    {
        dispatch(new UpdateApiDataJob());

        $this->info('UpdateApiDataJob dispatched.');
    }
}
