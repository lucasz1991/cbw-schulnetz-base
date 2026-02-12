<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class CleanActivityLogs extends Command
{
    protected $signature = 'activity:clean-old
                            {--days=7 : Loescht Eintraege aelter als X Tage}
                            {--dry-run : Zeigt nur die Anzahl, loescht aber nichts}';

    protected $description = 'Loescht alte Activity-Logs (standardmaessig aelter als 7 Tage).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = Activity::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("Keine Activity-Logs aelter als {$days} Tage gefunden.");
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-Run: {$count} Activity-Logs waeren geloescht worden (vor {$cutoff->toDateTimeString()}).");
            return Command::SUCCESS;
        }

        $deleted = 0;

        $query->orderBy('id')->chunkById(1000, function ($activities) use (&$deleted) {
            $ids = $activities->pluck('id');
            $deleted += Activity::whereIn('id', $ids)->delete();
        });

        $this->info("Fertig. {$deleted} Activity-Logs geloescht (aelter als {$days} Tage).");

        return Command::SUCCESS;
    }
}
