<?php

namespace App\Console\Commands;

use App\Jobs\ReportBook\CheckReportBooks;
use App\Models\ReportBook;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchCompleteReportBooks extends Command
{
    protected $signature = 'reportbooks:dispatch-complete-check
                            {--chunk=200 : Anzahl ReportBooks pro Job}
                            {--delay=0 : Delay in Minuten pro Job}
                            {--dry-run : Nur anzeigen, nichts dispatchen}';

    protected $description = 'Findet vollstaendig eingereichte Berichtshefte und dispatcht CheckReportBooks dafuer.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $delayMinutes = max(0, (int) $this->option('delay'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Suche passende Berichtshefte...');

        $ids = [];

        ReportBook::query()
            ->with([
                'days:id,course_id,date',
                'entries:id,report_book_id,entry_date,status',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($books) use (&$ids) {
                foreach ($books as $book) {
                    if ($this->shouldDispatch($book)) {
                        $ids[] = $book->id;
                    }
                }
            });

        $total = count($ids);

        if ($total === 0) {
            $this->warn('Keine passenden Berichtshefte gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$total} Berichtshefte.");

        $chunks = array_chunk($ids, $chunkSize);

        foreach ($chunks as $index => $chunkIds) {
            $chunkNo = $index + 1;
            $chunkCount = count($chunkIds);
            $this->line("Chunk {$chunkNo}: {$chunkCount} IDs.");

            if ($dryRun) {
                continue;
            }

            $job = new CheckReportBooks($chunkIds);

            if ($delayMinutes > 0) {
                $job->delay(now()->addMinutes($delayMinutes));
            }

            dispatch($job);
        }

        if ($dryRun) {
            $this->info('Dry-Run beendet. Es wurden keine Jobs dispatcht.');
            return Command::SUCCESS;
        }

        $this->info('Alle CheckReportBooks-Jobs wurden dispatcht.');
        return Command::SUCCESS;
    }

    private function shouldDispatch(ReportBook $book): bool
    {
        if ($book->entries->isEmpty()) {
            return false;
        }

        if ($book->entries->contains(fn ($entry) => (int) $entry->status < 1)) {
            return false;
        }

        // Mindestens ein Entry muss noch Status 1 sein (nicht bereits vollstaendig geprueft).
        if (! $book->entries->contains(fn ($entry) => (int) $entry->status === 1)) {
            return false;
        }

        $expectedDays = $book->days
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        if (empty($expectedDays)) {
            return false;
        }

        $existingDays = $book->entries
            ->pluck('entry_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        $missingDays = array_diff($expectedDays, $existingDays);

        return empty($missingDays);
    }
}
