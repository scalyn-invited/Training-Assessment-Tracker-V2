<?php

namespace App\Jobs;

use App\Services\DocumentExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $extractionId)
    {
        $this->onQueue('extraction');
    }

    public function handle(DocumentExtractionService $service): void
    {
        $service->process($this->extractionId);
    }
}
