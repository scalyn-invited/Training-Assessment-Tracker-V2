<?php

namespace App\Jobs;

use App\Services\LearningNotices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLearningNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $noticeId)
    {
        $this->onQueue('notifications');
    }

    public function handle(LearningNotices $notices): void
    {
        $notices->deliver($this->noticeId);
    }
}
