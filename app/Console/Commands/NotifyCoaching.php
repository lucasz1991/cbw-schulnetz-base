<?php

namespace App\Console\Commands;

use App\Services\Coaching\NoticeService;
use Illuminate\Console\Command;

class NotifyCoaching extends Command
{
    protected $signature = 'coaching:notify';
    protected $description = 'Ausstehende Einzelcoaching-Systemmitteilungen und E-Mails zustellen';
    public function handle(NoticeService $notices): int
    {
        $this->info($notices->deliverPending().' Versandvorgänge geprüft.');
        return self::SUCCESS;
    }
}
