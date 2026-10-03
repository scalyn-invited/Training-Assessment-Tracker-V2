<?php

namespace App\Jobs;

use App\Services\FileScanning;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanEvidence implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $fileId)
    {
        $this->onQueue('extraction');
    }

    public function handle(FileScanning $scanner): void
    {
        $scanner->inspect($this->fileId);
    }
}
