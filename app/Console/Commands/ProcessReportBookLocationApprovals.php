<?php

namespace App\Console\Commands;

use App\Services\ReportBooks\ApprovalRunner;
use Illuminate\Console\Command;

class ProcessReportBookLocationApprovals extends Command
{
    protected $signature = 'reportbooks:process-location-approvals';

    protected $description = 'Verarbeitet fällige, standortbezogene Berichtsheft-Freigaben aus dem festgehaltenen Bestand.';

    public function handle(ApprovalRunner $runner): int
    {
        $failed = false;
        foreach ($runner->runDue() as $runId => $result) {
            $this->line("Freigabelauf {$runId}: {$result['status']}");
            $failed = $failed || $result['status'] === 'failed';
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
