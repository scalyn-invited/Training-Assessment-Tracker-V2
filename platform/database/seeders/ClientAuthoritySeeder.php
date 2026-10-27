<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\MockIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit synthetic fixture authority, never an existing-data backfill. */
class ClientAuthoritySeeder extends Seeder
{
    public function run(): void
    {
        app(MockIdentity::class)->assertEnabled();
        $admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $at = now()->subDay();
        foreach (['admin' => 'admin', 'coordinator-a' => 'coordinator', 'coordinator-b' => 'coordinator', 'member-a' => 'member', 'member-b' => 'member'] as $name => $role) {
            $u = User::where('email', $name.'@example.invalid')->where('is_synthetic', true)->where('environment', 'test')->firstOrFail();
            $id = DB::table('client_memberships')->where('user_id', $u->id)->where('organisation_id', $admin->organisation_id)->value('id');
            if ($id) {
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('client_memberships')->insert(['id' => $id, 'user_id' => $u->id, 'organisation_id' => $admin->organisation_id,
                'environment' => 'test', 'joined_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
            $group = $role === 'coordinator' ? DB::table('coordinator_assignments')->where('user_id', $u->id)->value('group_id') : null;
            DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $u->id,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'), 'organisation_id' => $admin->organisation_id,
                'environment' => 'test', 'client_membership_id' => $id, 'group_id' => $group,
                'scope_type' => $group ? 'team' : 'client', 'scope_key' => $group ? 'team:'.$group : 'client:'.$admin->organisation_id,
                'starts_at' => $at, 'active_slot' => 1, 'granted_by' => $admin->id, 'reason' => 'Explicit synthetic fixture authority', 'created_at' => $at, 'updated_at' => $at]);
            if ($role === 'member') {
                $group = DB::table('memberships')->where('user_id', $u->id)->value('group_id');
                DB::table('client_team_memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $admin->organisation_id,
                    'environment' => 'test', 'client_membership_id' => $id, 'group_id' => $group, 'starts_at' => $at, 'active_slot' => 1, 'created_at' => $at, 'updated_at' => $at]);
            }
        }
        DB::table('groups')->where('organisation_id', $admin->organisation_id)->update(['kind' => 'team']);
        foreach (['a', 'b'] as $suffix) {
            $member = User::where('email', 'member-'.$suffix.'@example.invalid')->firstOrFail();
            $coordinator = User::where('email', 'coordinator-'.$suffix.'@example.invalid')->firstOrFail();
            $mid = DB::table('client_memberships')->where('user_id', $member->id)->value('id');
            if (DB::table('coordinator_allocations')->where('member_membership_id', $mid)->exists()) {
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('coordinator_allocations')->insert(['id' => $id, 'organisation_id' => $admin->organisation_id, 'environment' => 'test',
                'member_membership_id' => $mid, 'coordinator_membership_id' => DB::table('client_memberships')->where('user_id', $coordinator->id)->value('id'),
                'kind' => 'primary', 'primary_slot' => 1, 'active_slot' => 1, 'starts_at' => $at, 'assigned_by' => $admin->id,
                'reason' => 'Explicit synthetic primary allocation', 'created_at' => $at, 'updated_at' => $at]);
            DB::table('client_sensitive_grants')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $admin->organisation_id,
                'environment' => 'test', 'allocation_id' => $id, 'granted_by' => $admin->id, 'starts_at' => $at,
                'active_slot' => 1, 'reason' => 'Explicit fixture sensitive grant by a different Client Admin', 'created_at' => $at, 'updated_at' => $at]);
        }
    }
}
