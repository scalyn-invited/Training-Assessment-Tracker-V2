<?php

namespace App\Services\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientContext
{
    public ?string $id = null;

    public function choices(User $actor)
    {
        $access = app(ClientAccess::class);

        return Organisation::where('active', true)
            ->when(Schema::hasTable('platform_client_states'), fn ($q) => $q->whereNotIn('id', DB::table('platform_client_states')->where('environment', config('training.environment'))->where('suspended', true)->select('organisation_id')))
            ->when($access->globalAdmin($actor) && Schema::hasTable('platform_client_states'), fn ($q) => $q->whereIn('id', app(PlatformAdministration::class)->clients()->select('id')))
            ->when(! $access->globalAdmin($actor),
                fn ($q) => $q->whereIn('id', $access->activeMemberships()->where('cm.user_id', $actor->id)->select('cm.organisation_id')))
            ->orderBy('name')->get(['id', 'name']);
    }

    public function select(User $actor, string $id): string
    {
        abort_unless($this->choices($actor)->contains('id', $id), 404, 'Client unavailable.');

        return $this->id = $id;
    }

    public function resolve(User $actor, ?string $recordClient = null): ?string
    {
        if ($recordClient !== null && ! request()->route()) {
            return $this->choices($actor)->contains('id', $recordClient) ? $this->id = $recordClient : null;
        }
        // HTTP context is set by middleware. Console workers bind their persisted client explicitly.
        if ($this->id !== null) {
            if ($recordClient !== null && $recordClient !== $this->id) {
                return null;
            }

            return $this->choices($actor)->contains('id', $this->id) ? $this->id : null;
        }
        $choices = $this->choices($actor);

        return $choices->count() === 1 ? $choices->first()->id : null;
    }
}
