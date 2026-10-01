<?php

namespace App\Jobs;

use App\Services\Ai\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateBlock implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public string $blockId)
    {
        $this->onQueue('ai_generation');
    }

    public function handle(Generation $generation): void
    {
        $generation->process($this->blockId);
    }
}
