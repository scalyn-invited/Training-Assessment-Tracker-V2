<?php

namespace App\Jobs;

use App\Models\Promotion;
use App\Models\User;
use App\Services\Access;
use App\Services\Promotions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $actor = User::findOrFail($this->actorId);
        $access = app(Access::class);
        if (! $promotion->organisation_id || ! $access->client($actor, $promotion->organisation_id) || ! $access->admin($actor)) {
            $promotion->update(['status' => 'cancelled']);

            return;
        }
        try {
            $service->send($actor, $promotion, $this->reconcile);
        } catch (HttpException $error) {
            $promotion->update(['status' => $promotion->fresh()->status === 'sending' ? 'ambiguous' : 'cancelled']);
        }
    }
}
