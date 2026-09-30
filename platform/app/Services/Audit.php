<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Audit
{
    public static function record(User $actor, string $action, string $target, array $metadata = []): void
    {
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'organisation_id' => $actor->organisation_id,
            'environment' => $actor->environment, 'actor_id' => $actor->id, 'action' => $action,
            'target_id' => $target, 'request_id' => request()->attributes->get('request_id') ?? (string) Str::uuid(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
