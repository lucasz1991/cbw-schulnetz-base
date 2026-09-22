<?php

namespace App\Console\Commands;

use App\Services\Coaching\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncCoaching extends Command
{
    protected $signature = 'coaching:sync';
    protected $description = 'Opt-in Einzelcoachings aus UVS abgleichen und bestätigte Gesamtpläne übertragen';

    public function handle(SyncService $sync): int
    {
        if (!config('coaching.enabled') || !Schema::hasTable('coaching_contracts')) { $this->info('Einzelcoaching-Abgleich ist ausgeschaltet.'); return self::SUCCESS; }
        $lock = Cache::lock('coaching:sync', 600);
        if (!$lock->get()) { $this->info('Ein Abgleich läuft bereits.'); return self::SUCCESS; }
        try {
            $count = $sync->import();
            $sent = $sync->sendPending();
            $this->info("{$count} Vertragsdatensätze abgeglichen; {$sent} Gesamtpläne übernommen.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Payloads may contain personal information; expose only a bounded operational error.
            $this->error('Einzelcoaching-Abgleich fehlgeschlagen. API-Verbindung, Berechtigungen und Erweiterung prüfen.');
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
