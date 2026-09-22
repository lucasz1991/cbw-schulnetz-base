<?php

namespace App\Jobs;

use App\Services\Coaching\NoticeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverCoachingNotices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 120;
    public function __construct(public int $contractId) {}
    public function handle(NoticeService $notices): void { $notices->deliverPending($this->contractId); }
}
