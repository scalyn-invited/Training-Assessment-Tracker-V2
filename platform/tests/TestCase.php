<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function explicitMember(User $user, string $client): string
    {
        $id = (string) Str::uuid();
        DB::table('client_memberships')->insert(['id' => $id, 'organisation_id' => $client,
            'environment' => $user->environment, 'user_id' => $user->id, 'joined_at' => now()->subDay()]);
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(),
            'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', 'member')->value('id'),
            'organisation_id' => $client, 'environment' => $user->environment, 'client_membership_id' => $id,
            'scope_type' => 'client', 'scope_key' => 'client:'.$client, 'starts_at' => now()->subDay(), 'active_slot' => 1,
            'granted_by' => $user->id, 'reason' => 'Explicit isolated contract fixture']);

        return $id;
    }
}
