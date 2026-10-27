<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Offline synthetic rehearsal only. Not an application authorization/bootstrap API. */
final class ClientBackfill
{
    public const KEY = 'client-foundation-v1';

    private const NEW_TABLES = ['client_sensitive_grants', 'roles', 'client_memberships', 'client_team_memberships', 'role_assignments', 'coordinator_allocations', 'client_rehearsal_control'];

    public static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function guard(string $expected): void
    {
        if (! app()->environment('testing') || config('training.environment') !== 'test'
            || DB::connection()->getDatabaseName() !== $expected) {
            throw new RuntimeException('Wrong disposable database or environment.');
        }
        if (DB::getDriverName() === 'sqlite') {
            if ($expected === ':memory:' && app()->runningUnitTests()) {
                return;
            }
            $path = realpath($expected);
            $root = realpath(base_path('.runtime/client-backfill'));
            if (! $path || ! $root || ! str_starts_with(strtolower(str_replace('\\', '/', $path)), strtolower(str_replace('\\', '/', $root)).'/')
                || $path === realpath(database_path('database.sqlite'))) {
                throw new RuntimeException('SQLite target must be an existing disposable client-backfill copy.');
            }
        } elseif (DB::getDriverName() === 'mariadb') {
            require_once base_path('tests/Support/client-qualification-guard.php');
            assertClientQualificationDatabase();
        } else {
            throw new RuntimeException('Unsupported rehearsal database.');
        }
    }

    public static function snapshot(?string $omitAudit = null): array
    {
        $result = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, self::NEW_TABLES, true) || str_starts_with($table, 'sqlite_')) {
                continue;
            }
            $rows = DB::table($table)->when($table === 'audit_events' && $omitAudit,
                fn ($q) => $q->where('id', '!=', $omitAudit))->get()->map(function ($row) use ($table) {
                    $values = (array) $row;
                    if ($table === 'groups') {
                        unset($values['kind']);
                    }
                    ksort($values);

                    return self::hash($values);
                })->sort()->values()->all();
            $result[$table] = ['count' => count($rows), 'sha256' => self::hash($rows)];
        }
        ksort($result);

        return $result;
    }

    /** Derive only the explicitly approved all-team, current-role, existing-primary mapping. */
    public static function mapping(string $at): array
    {
        $users = DB::table('users')->orderBy('id')->get()->keyBy('id');
        $groups = DB::table('groups')->orderBy('id')->get()->keyBy('id');
        if ($users->isEmpty() || $groups->isEmpty()) {
            throw new RuntimeException('Empty mapping source.');
        }
        if ($users->pluck('organisation_id')->unique()->count() !== 1 || DB::table('organisations')->count() !== 1) {
            throw new RuntimeException('This approved rehearsal manifest supports exactly one source client.');
        }
        $members = $teams = $grants = $primaries = [];
        foreach ($users as $u) {
            if (! $u->is_synthetic || $u->environment !== 'test' || ! $u->active
                || ! in_array($u->role, ['admin', 'coordinator', 'member'], true)
                || ! DB::table('organisations')->where('id', $u->organisation_id)->where('active', true)->exists()) {
                throw new RuntimeException('Non-synthetic, inactive or unresolved user/client.');
            }
            $members[] = ['user_id' => $u->id, 'organisation_id' => $u->organisation_id];
            if ($u->role !== 'coordinator') {
                $grants[] = ['user_id' => $u->id, 'organisation_id' => $u->organisation_id, 'role' => $u->role, 'group_id' => null];
            }
        }
        foreach ($groups as $g) {
            if ($g->environment !== 'test' || ! $g->active || ! DB::table('organisations')->where('id', $g->organisation_id)->where('active', true)->exists()) {
                throw new RuntimeException('Unresolved group.');
            }
        }
        foreach (['memberships', 'coordinator_assignments'] as $table) {
            foreach (DB::table($table)->orderBy('id')->get() as $r) {
                $u = $users[$r->user_id] ?? null;
                $g = $groups[$r->group_id] ?? null;
                if (! $u || ! $g || $r->organisation_id !== $u->organisation_id || $r->organisation_id !== $g->organisation_id
                    || $r->environment !== 'test' || $r->ends_at !== null || $r->starts_at > $at) {
                    throw new RuntimeException('Foreign-client, inactive or ambiguous legacy relationship.');
                }
                if ($table === 'memberships') {
                    $teams[] = ['user_id' => $u->id, 'organisation_id' => $u->organisation_id, 'group_id' => $g->id];
                } else {
                    if ($u->role !== 'coordinator') {
                        throw new RuntimeException('Coordinator role mismatch.');
                    }
                    $grants[] = ['user_id' => $u->id, 'organisation_id' => $u->organisation_id, 'role' => 'coordinator', 'group_id' => $g->id];
                }
            }
        }
        foreach (DB::table('enrolments')->orderBy('id')->get() as $e) {
            $member = $users[$e->member_id] ?? null;
            $coordinator = $users[$e->coordinator_id] ?? null;
            $group = $groups[$e->group_id] ?? null;
            if (! $member || ! $coordinator || ! $group || $member->id === $coordinator->id || $member->role !== 'member'
                || $coordinator->role !== 'coordinator' || $e->environment !== 'test'
                || $e->organisation_id !== $member->organisation_id || $e->organisation_id !== $coordinator->organisation_id
                || $e->organisation_id !== $group->organisation_id
                || ! collect($grants)->contains(fn ($g) => $g['user_id'] === $coordinator->id && $g['group_id'] === $group->id)) {
                throw new RuntimeException('Invalid or unallocated enrolment ownership.');
            }
            $key = $e->organisation_id.':'.$e->member_id;
            $value = ['organisation_id' => $e->organisation_id, 'member_id' => $e->member_id, 'coordinator_id' => $e->coordinator_id];
            if (isset($primaries[$key]) && $primaries[$key] !== $value) {
                throw new RuntimeException('Conflicting primary coordinator history.');
            }
            $primaries[$key] = $value;
        }
        foreach ([$teams, $grants] as $rows) {
            if (count(array_unique(array_map(self::hash(...), $rows))) !== count($rows)) {
                throw new RuntimeException('Duplicate source relationship.');
            }
        }

        return ['memberships' => $members, 'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'organisation_id' => $g->organisation_id, 'kind' => 'team'])->values()->all(),
            'teams' => $teams, 'grants' => $grants, 'primaries' => array_values($primaries)];
    }

    public static function draft(string $sourceHash, string $at): array
    {
        return ['version' => 1, 'key' => self::KEY, 'source_sha256' => $sourceHash, 'effective_at_utc' => $at,
            'audit_id' => (string) Str::uuid(), 'legacy_sha256' => self::hash(self::snapshot()), 'mapping' => self::mapping($at)];
    }

    /** Harness preparation only: never creates or upgrades application schema. */
    public static function prepare(string $expected, array $manifest): void
    {
        self::guard($expected);
        if (Schema::hasTable('client_rehearsal_control')) {
            throw new RuntimeException('Rehearsal already prepared.');
        }
        Schema::create('client_rehearsal_control', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('source_sha256', 64);
            $t->string('manifest_sha256', 64);
            $t->string('legacy_sha256', 64);
            $t->timestamp('completed_at')->nullable();
        });
        DB::table('client_rehearsal_control')->insert(['id' => self::KEY, 'source_sha256' => $manifest['source_sha256'],
            'manifest_sha256' => self::hash($manifest), 'legacy_sha256' => $manifest['legacy_sha256']]);
    }

    public static function run(array $m, string $hash, string $sourceHash, string $expected, bool $apply, ?Closure $checkpoint = null): array
    {
        self::guard($expected);
        if (! hash_equals(self::hash($m), $hash) || ($m['source_sha256'] ?? null) !== $sourceHash
            || ($m['version'] ?? null) !== 1 || ($m['key'] ?? null) !== self::KEY
            || ! preg_match('/^[a-f0-9]{64}$/', $sourceHash)
            || ! Str::isUuid($m['audit_id'] ?? '')
            || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $m['effective_at_utc'] ?? '')
            || $m['effective_at_utc'] > now()->utc()->format('Y-m-d H:i:s')) {
            throw new RuntimeException('Manifest/source hash, version or timestamp mismatch.');
        }

        return DB::transaction(function () use ($m, $hash, $sourceHash, $apply, $checkpoint) {
            // Use the same ordered client locks as live directory mutations; SQLite serializes writers.
            foreach (collect($m['mapping']['memberships'])->pluck('organisation_id')->unique()->sort()->values() as $id) {
                DB::table('organisations')->where('id', $id)->lockForUpdate()->first();
            }
            $control = DB::table('client_rehearsal_control')->where('id', self::KEY)->lockForUpdate()->first();
            if (! $control || $control->source_sha256 !== $sourceHash || $control->manifest_sha256 !== $hash
                || $control->legacy_sha256 !== $m['legacy_sha256']) {
                throw new RuntimeException('Unprepared target or conflicting manifest.');
            }
            // Revalidate synthetic eligibility even for an idempotent retry, but never revive revoked grants.
            if (DB::table('users')->where(fn ($q) => $q->where('is_synthetic', '!=', 1)->orWhereNull('is_synthetic')
                ->orWhere('environment', '!=', 'test')->orWhereNull('environment'))->exists()) {
                throw new RuntimeException('Non-synthetic source.');
            }
            if ($control->completed_at !== null) {
                return ['status' => 'already_applied', 'writes' => 0, 'manifest_sha256' => $hash];
            }
            if (self::hash(self::snapshot()) !== $m['legacy_sha256'] || self::mapping($m['effective_at_utc']) !== $m['mapping']) {
                throw new RuntimeException('Source content or approved mapping changed.');
            }
            foreach (['client_memberships', 'client_team_memberships', 'role_assignments', 'coordinator_allocations'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Conflicting existing foundation records.');
                }
            }
            if (DB::table('groups')->whereNotNull('kind')->exists() || DB::table('audit_events')->where('id', $m['audit_id'])->exists()) {
                throw new RuntimeException('Conflicting group classification or audit identity.');
            }
            if (! $apply) {
                return ['status' => 'validated', 'writes' => 0, 'manifest_sha256' => $hash];
            }
            $at = $m['effective_at_utc'];
            $mapping = $m['mapping'];
            $ids = [];
            $actors = [];
            foreach ($mapping['grants'] as $g) {
                if ($g['role'] === 'admin') {
                    $actors[$g['organisation_id']] = $g['user_id'];
                }
            }
            foreach ($mapping['memberships'] as $row) {
                if (! isset($actors[$row['organisation_id']])) {
                    throw new RuntimeException('Explicit client administrator attribution required.');
                }
                $id = (string) Str::uuid();
                $ids[$row['user_id']] = $id;
                DB::table('client_memberships')->insert(['id' => $id, 'environment' => 'test', 'joined_at' => $at,
                    'created_at' => $at, 'updated_at' => $at] + $row);
            }
            foreach ($mapping['groups'] as $row) {
                DB::table('groups')->where('id', $row['id'])->update(['kind' => 'team']);
            }
            $checkpoint && $checkpoint('memberships');
            foreach ($mapping['teams'] as $row) {
                DB::table('client_team_memberships')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $row['organisation_id'],
                    'environment' => 'test', 'client_membership_id' => $ids[$row['user_id']], 'group_id' => $row['group_id'],
                    'starts_at' => $at, 'active_slot' => 1, 'created_at' => $at, 'updated_at' => $at]);
            }
            foreach ($mapping['grants'] as $row) {
                DB::table('role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $row['user_id'],
                    'role_id' => DB::table('roles')->where('code', $row['role'])->value('id'), 'organisation_id' => $row['organisation_id'],
                    'environment' => 'test', 'client_membership_id' => $ids[$row['user_id']], 'group_id' => $row['group_id'],
                    'scope_type' => $row['group_id'] ? 'team' : 'client', 'scope_key' => $row['group_id'] ? 'team:'.$row['group_id'] : 'client:'.$row['organisation_id'],
                    'starts_at' => $at, 'active_slot' => 1, 'granted_by' => $actors[$row['organisation_id']],
                    'reason' => 'Approved offline synthetic rehearsal '.$hash, 'created_at' => $at, 'updated_at' => $at]);
            }
            foreach ($mapping['primaries'] as $row) {
                DB::table('coordinator_allocations')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $row['organisation_id'],
                    'environment' => 'test', 'member_membership_id' => $ids[$row['member_id']], 'coordinator_membership_id' => $ids[$row['coordinator_id']],
                    'kind' => 'primary', 'primary_slot' => 1, 'active_slot' => 1, 'starts_at' => $at, 'assigned_by' => $actors[$row['organisation_id']],
                    'reason' => 'Approved offline synthetic rehearsal '.$hash, 'created_at' => $at, 'updated_at' => $at]);
            }
            DB::table('audit_events')->insert(['id' => $m['audit_id'], 'organisation_id' => array_key_first($actors),
                'environment' => 'test', 'action' => 'client.rehearsal.applied',
                'target_id' => self::KEY, 'request_id' => $m['audit_id'], 'metadata' => json_encode(['manifest_sha256' => $hash,
                    'source_sha256' => $sourceHash, 'operator' => 'Bernie-approved offline rehearsal; not an authenticated application action']), 'created_at' => $at]);
            if (self::hash(self::snapshot($m['audit_id'])) !== $m['legacy_sha256']) {
                throw new RuntimeException('Legacy preservation check failed.');
            }
            DB::table('client_rehearsal_control')->where('id', self::KEY)->update(['completed_at' => $at]);

            return ['status' => 'applied', 'manifest_sha256' => $hash, 'legacy_preserved' => true];
        }, 3);
    }
}
