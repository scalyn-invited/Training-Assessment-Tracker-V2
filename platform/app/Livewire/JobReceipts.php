<?php

namespace App\Livewire;

use App\Models\OutboxEvent;
use App\Services\Access;
use Livewire\Component;

class JobReceipts extends Component
{
    public function render()
    {
        $user = auth()->user();
        abort_unless($user && app(Access::class)->active($user), 401);
        $jobs = OutboxEvent::where('actor_id', $user->id)->where('organisation_id', $user->organisation_id)->where('environment', $user->environment)
            ->whereIn('enrolment_id', app(Access::class)->enrolments($user)->select('id'))->latest()->limit(5)->get();

        return view('livewire.job-receipts', compact('jobs'));
    }
}
