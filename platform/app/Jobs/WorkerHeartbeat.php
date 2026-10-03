<?php

namespace App\Jobs;

use App\Services\Operations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        app(Operations::class)->heartbeat('worker');
    }
}
