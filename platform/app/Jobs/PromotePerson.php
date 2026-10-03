<?php

namespace App\Jobs;

use App\Models\Promotion;
use App\Models\User;
use App\Services\Promotions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PromotePerson implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $promotionId, public string $actorId, public bool $reconcile = false)
    {
        $this->onQueue('integration');
    }

    public function handle(Promotions $service): void
    {
        $promotion = Promotion::findOrFail($this->promotionId);
        if ($promotion->status !== ($this->reconcile ? 'reconcile_queued' : 'queued')) {
            return;
        }
        $service->send(User::findOrFail($this->actorId), $promotion, $this->reconcile);
    }
}
