<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\Tenancy\ClientAdministration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit synthetic bootstrap only; ordinary relationships use domain services. */
class AuthorityFactory
{
    public function bootstrapAdmin(User $admin): object
    {
        if (! config('demo.enabled') || ! $admin->is_synthetic || $admin->environment !== 'test') {
            throw new \RuntimeException('Synthetic demo bootstrap only.');
        }
        $id = (string) Str::uuid();
        DB::table('client_memberships')->insert(['id' => $id, 'organisation_id' => $admin->organisation_id, 'environment' => 'test', 'user_id' => $admin->id, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'organisation_id' => $admin->organisation_id, 'environment' => 'test', 'client_membership_id' => $id, 'scope_type' => 'client', 'scope_key' => 'client:'.$admin->organisation_id, 'starts_at' => now(), 'active_slot' => 1, 'granted_by' => $admin->id, 'reason' => 'Explicit synthetic Client Admin bootstrap, no Global Admin', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('client_memberships')->where('id', $id)->first();
    }

    public function member(User $admin, User $user, string $role, ?string $team = null): object
    {
        $service = app(ClientAdministration::class);
        $m = $service->join($admin, $admin->organisation_id, $user->id, 'Synthetic scenario membership');
        $service->grantRole($admin, $admin->organisation_id, $m->id, $role, $team, 'Synthetic scoped role');

        return $m;
    }
}
