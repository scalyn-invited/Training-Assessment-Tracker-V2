<?php

namespace App\Services\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use App\Services\LocalAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformAdministration
{
    public function environment(): string
    {
        return config('training.environment');
    }

    public function lock(): object
    {
        DB::table('platform_settings')->insertOrIgnore(['environment' => $this->environment(), 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('platform_settings')->where('environment', $this->environment())->lockForUpdate()->first();
    }

    public function authorised(User $actor): void
    {
        abort_unless(app(ClientAccess::class)->globalAdmin($actor), 403);
    }

    public function audit(?User $actor, string $action, string $target, string $reason, array $changes = [], ?string $operator = null): void
    {
        DB::table('platform_audit_events')->insert(['id' => (string) Str::uuid(), 'environment' => $this->environment(),
            'actor_id' => $actor?->id, 'operator_reference' => $operator, 'action' => $action, 'target_id' => $target,
            'reason' => $reason, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function clients()
    {
        return Organisation::query()->where(function ($q) {
            $q->whereIn('id', DB::table('client_memberships')->where('environment', $this->environment())->select('organisation_id'))
                ->orWhereIn('id', DB::table('groups')->where('environment', $this->environment())->select('organisation_id'))
                ->orWhereIn('id', DB::table('platform_client_states')->where('environment', $this->environment())->select('organisation_id'));
        });
    }

    public function accounts()
    {
        return User::where('environment', $this->environment())->where('active', true)
            ->when($this->environment() === 'production', fn ($q) => $q->where('is_synthetic', false))
            ->whereIn('id', DB::table('identities')->where('active', true)->select('user_id'));
    }

    public function admins()
    {
        return DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->where('ra.environment', $this->environment())->where('r.code', 'admin')->where('ra.scope_type', 'platform')
            ->whereNull('ra.organisation_id')->whereNull('ra.client_membership_id')->whereNull('ra.group_id')
            ->whereNull('ra.ends_at')->where('ra.starts_at', '<=', now());
    }

    private function reason(string $reason): void
    {
        Validator::make(compact('reason'), ['reason' => 'required|string|min:8|max:2000'])->validate();
    }

    public function appoint(?User $actor, string $id, string $reason, ?string $operator = null): void
    {
        $this->reason($reason);
        if (! $actor) {
            Validator::make(['operator' => $operator], ['operator' => 'required|string|max:160'])->validate();
        }
        DB::transaction(function () use ($actor, $id, $reason, $operator) {
            $this->lock();
            if ($actor) {
                $this->authorised($actor);
            }
            $user = $this->accounts()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(app(ClientAccess::class)->active($user), 403);
            if ($this->admins()->where('ra.user_id', $id)->exists()) {
                return;
            }
            // The operator entry point bootstraps only; subsequent appointments use the UI.
            abort_if(! $actor && $this->admins()->exists(), 409, 'A Global Admin already exists. Use platform administration.');
            $grant = (string) Str::uuid();
            DB::table('role_assignments')->insert(['id' => $grant, 'user_id' => $id,
                'role_id' => DB::table('roles')->where('code', 'admin')->value('id'), 'environment' => $this->environment(),
                'scope_type' => 'platform', 'scope_key' => 'platform', 'starts_at' => now(), 'active_slot' => 1,
                'granted_by' => $actor?->id ?? $id, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            SensitiveAccess::invalidate([$id]);
            $this->audit($actor, 'platform.admin.granted', $id, $reason, ['grant_id' => $grant], $operator);
        }, 3);
    }

    public function revoke(User $actor, string $grant, string $reason): void
    {
        $this->reason($reason);
        DB::transaction(function () use ($actor, $grant, $reason) {
            $this->lock();
            $this->authorised($actor);
            $row = $this->admins()->where('ra.id', $grant)->first('ra.*');
            abort_unless($row, 409, 'This Global Admin assignment changed. Reload.');
            $remaining = $this->admins()->where('ra.id', '!=', $grant)->pluck('ra.user_id')->filter(fn ($id) => ($u = User::find($id)) && app(ClientAccess::class)->active($u));
            abort_if($remaining->isEmpty(), 409, 'The last active Global Admin cannot be removed.');
            DB::table('role_assignments')->where('id', $grant)->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
            SensitiveAccess::invalidate([$row->user_id]);
            $this->audit($actor, 'platform.admin.revoked', $row->user_id, $reason, ['grant_id' => $grant]);
        }, 3);
    }

    public function client(User $actor, ?string $id, string $name, int $expected, string $reason, ?bool $suspended = null): string
    {
        $this->reason($reason);
        Validator::make(compact('name'), ['name' => 'required|string|max:160'])->validate();

        return DB::transaction(function () use ($actor, $id, $name, $expected, $reason, $suspended) {
            $this->lock();
            $this->authorised($actor);
            if ($id) {
                $org = $this->clients()->whereKey($id)->lockForUpdate()->firstOrFail();
            } else {
                abort_unless($expected === 0, 409);
                $org = Organisation::create(['id' => (string) Str::uuid(), 'name' => trim($name), 'active' => true]);
            }
            $state = DB::table('platform_client_states')->where('organisation_id', $org->id)->where('environment', $this->environment())->first();
            abort_unless((int) ($state->version ?? 0) === $expected, 409, 'Client changed. Reload before saving.');
            // A directory name is shared; never silently rename an organisation used by another environment.
            abort_if($org->name !== trim($name) && DB::table('client_memberships')->where('organisation_id', $org->id)->where('environment', '!=', $this->environment())->exists(), 409, 'This directory name is shared across environments. Coordinate its rename separately.');
            $before = ['name' => $org->name, 'suspended' => (bool) ($state->suspended ?? false)];
            $org->update(['name' => trim($name)]);
            $value = $suspended ?? (bool) ($state->suspended ?? false);
            DB::table('platform_client_states')->updateOrInsert(['organisation_id' => $org->id, 'environment' => $this->environment()],
                ['suspended' => $value, 'version' => $expected + 1, 'created_at' => $state->created_at ?? now(), 'updated_at' => now()]);
            if ($value && ! $before['suspended']) {
                $this->suspend($org->id);
            }
            $this->audit($actor, $id ? 'platform.client.updated' : 'platform.client.created', $org->id, $reason,
                ['before' => $before, 'after' => ['name' => trim($name), 'suspended' => $value]]);

            return $org->id;
        }, 3);
    }

    private function suspend(string $client): void
    {
        $env = $this->environment();
        SensitiveAccess::invalidate(DB::table('client_memberships')->where('organisation_id', $client)->where('environment', $env)->pluck('user_id')->all());
        DB::table('client_sensitive_grants')->where('organisation_id', $client)->where('environment', $env)->whereNull('ends_at')
            ->update(['ends_at' => now(), 'active_slot' => null, 'updated_at' => now()]);
        $enrolments = DB::table('enrolments')->where('organisation_id', $client)->where('environment', $env)->select('id');
        $submissions = DB::table('submission_attempts')->whereIn('enrolment_id', $enrolments)->select('id');
        DB::table('appeals')->whereIn('submission_attempt_id', $submissions)->where('status', 'open')->whereNull('authority_ended_at')->update(['authority_ended_at' => now(), 'updated_at' => now()]);
        DB::table('outbox_events')->where('organisation_id', $client)->where('environment', $env)->where('status', 'pending')->update(['status' => 'cancelled', 'error_code' => 'client_suspended']);
        DB::table('learning_notifications')->whereIn('enrolment_id', $enrolments)->whereIn('status', ['pending', 'sending'])->update(['status' => 'cancelled', 'error_code' => 'client_suspended']);
        $runs = DB::table('ai_runs')->where('organisation_id', $client)->where('environment', $env)->whereIn('status', ['queued', 'running']);
        DB::table('ai_blocks')->whereIn('ai_run_id', $runs->select('id'))->whereIn('status', ['queued', 'running'])->update(['status' => 'cancelled']);
        $runs->update(['status' => 'cancelled', 'message' => 'Client suspended. New work requires explicit submission.']);
        DB::table('document_extractions')->whereIn('evidence_file_id', DB::table('evidence_files')->whereIn('enrolment_id', $enrolments)->select('id'))->whereIn('status', ['queued', 'running', 'waiting_scan'])->update(['status' => 'cancelled']);
        DB::table('evidence_files')->whereIn('enrolment_id', $enrolments)->whereIn('scan_status', ['quarantined', 'scanning'])->update(['scan_status' => 'cancelled']);
        DB::table('promotions')->where('organisation_id', $client)->where('environment', $env)->whereIn('status', ['prepared', 'queued', 'reconcile_queued'])->update(['status' => 'cancelled']);
    }

    public function clientRole(User $actor, string $client, string $user, string $action, int $expected, string $reason): void
    {
        abort_if($action === 'join' && app(LocalAccounts::class)->enabled(), 409, 'Use an invitation so the account owner can accept access.');
        $this->reason($reason);
        DB::transaction(function () use ($actor, $client, $user, $action, $expected, $reason) {
            $this->lock();
            $this->authorised($actor);
            $this->clients()->whereKey($client)->lockForUpdate()->firstOrFail();
            $person = $this->accounts()->whereKey($user)->lockForUpdate()->firstOrFail();
            abort_unless($person->permission_version === $expected, 409, 'Account permissions changed. Reload.');
            $service = app(ClientAdministration::class);
            if ($action === 'join') {
                $service->join($actor, $client, $user, $reason);
            } else {
                $member = app(ClientAccess::class)->membership($person, $client);
                if (! $member) {
                    throw ValidationException::withMessages([
                        'client' => app(LocalAccounts::class)->enabled()
                            ? 'This account is not an active member of the selected client. Choose its current client, or invite the account and wait for acceptance before granting Client Admin.'
                            : 'This account is not an active member of the selected client. Choose its current client, or explicitly join the account to that client first.',
                    ]);
                }
                if ($action === 'grant') {
                    $service->grantRole($actor, $client, $member->id, 'admin', null, $reason);
                } elseif ($action === 'revoke') {
                    $grant = app(ClientAccess::class)->grants($person, 'admin')->where('ra.organisation_id', $client)->where('ra.scope_type', 'client')->first('ra.id');
                    abort_unless($grant, 409);
                    $service->revokeRole($actor, $client, $grant->id, $reason);
                } else {
                    abort(422);
                }
            }
            $this->audit($actor, 'platform.client.account.'.$action, $client, $reason, ['user_id' => $user]);
        }, 3);
    }
}
