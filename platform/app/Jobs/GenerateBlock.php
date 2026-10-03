<?php

namespace App\Jobs;

use App\Models\AiBlock;
use App\Models\AiRun;
use App\Services\Ai\Generation;
use App\Services\Ai\TaskGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateBlock implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public string $blockId, string $queue = 'ai_generation')
    {
        $this->onQueue($queue);
    }

    public function handle(Generation $generation): void
    {
        $block = AiBlock::findOrFail($this->blockId);
        $run = AiRun::findOrFail($block->ai_run_id);
        if ($run->configuration['task'] !== 'generate_programme') {
            app(TaskGateway::class)->process($this->blockId);

            return;
        }
        $generation->process($this->blockId);
    }
}
