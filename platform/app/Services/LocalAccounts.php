<?php

namespace App\Services;

use App\Models\Identity;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientAdministration;
use App\Services\Tenancy\PlatformAdministration;
use App\Services\Tenancy\SensitiveAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LocalAccounts
{
    public const ISSUER = 'urn:training:local';

    public function enabled(): bool
    {
        return config('training.authentication_mode') === 'local_password'
            && ((app()->environment(['local', 'testing']) && config('training.environment') === 'test')
                || (app()->environment('production') && config('training.environment') === 'production'
                    && config('training.localhost_production')));
    }

    public function assertEnabled(): void
    {
        abort_unless($this->enabled(), 503, 'Local password access is unavailable.');
    }

    private function eligible(User $user): void
    {
        abort_unless($user->active && $user->environment === config('training.environment')
            && ! ($user->environment === 'production' && $user->is_synthetic), 403);
    }

    public function identity(User $user): ?Identity
    {
        return Identity::where('user_id', $user->id)->where('issuer', self::ISSUER)
            ->where('subject', $user->id)->where('active', true)->first();
    }

    private function prepare(User $user): void
    {
        $this->eligible($user);
        abort_unless($user->origin === 'manual' && $user->sync_policy === 'local_only', 422, 'External accounts require separate identity review.');
        Identity::firstOrCreate(['user_id' => $user->id, 'issuer' => self::ISSUER, 'subject' => $user->id], ['active' => true]);
        abort_unless($this->identity($user), 403);
    }

    private function password(User $user, string $password): void
    {
        abort_unless($user->local_password_enabled_at && $this->identity($user)
            && Hash::check($password, $user->password), 403, 'Current password could not be confirmed.');
    }

    private function audit(User $actor, string $action, string $target, string $reason): void
    {
        app(PlatformAdministration::class)->audit($actor, 'local.'.$action, $target, $reason);
    }

    private function authorised(User $actor, ?string $client): void
    {
        $this->eligible($actor);
        $access = app(ClientAccess::class);
        abort_unless($client ? $access->administer($actor, $client) : $access->globalAdmin($actor), 403);
    }

    private function mutation(User $actor, ?string $client, string $password, string $reason, callable $operation): mixed
    {
        $this->assertEnabled();
        Validator::make(compact('reason'), ['reason' => 'required|string|min:8|max:2000'])->validate();
        try {
            return DB::transaction(function () use ($actor, $client, $password, $reason, $operation) {
                app(PlatformAdministration::class)->lock();
                if ($client) {
                    Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
                }
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $this->authorised($actor, $client);
                $this->password($actor, $password);

                return $operation($actor, $reason);
            }, 3);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                $this->audit($actor, 'authorisation.denied', $actor->id, 'Local account action denied');
            }
            throw $e;
        }
    }

    private function issue(User $actor, User $user, string $purpose, ?string $client, string $reason, bool $bootstrap = false): array
    {
        $query = DB::table('local_account_links')->where('user_id', $user->id)->where('purpose', $purpose)
            ->whereNull('consumed_at')->whereNull('revoked_at');
        // Invitations for unrelated clients are independent.
        if ($purpose === 'invite') {
            $query->where('organisation_id', $client);
        }
        $query->update(['revoked_at' => now()]);
        $raw = bin2hex(random_bytes(32));
        $id = (string) Str::uuid();
        DB::table('local_account_links')->insert(['id' => $id, 'token_hash' => hash('sha256', $raw),
            'purpose' => $purpose, 'user_id' => $user->id, 'issuer_id' => $actor->id,
            'issuer_version' => $actor->permission_version, 'organisation_id' => $client,
            'environment' => config('training.environment'), 'bootstrap' => $bootstrap,
            'expires_at' => $purpose === 'invite' ? now()->addDays(7) : now()->addHour(),
            'created_at' => now(), 'updated_at' => now()]);
        $this->audit($actor, $purpose.'.issued', $id, $reason);

        return ['id' => $id, 'purpose' => $purpose, 'token' => $raw,
            'url' => rtrim(config('app.url'), '/').($purpose === 'invite' ? '/invitations/' : '/password/'.$purpose.'/').$raw];
    }

    public function create(User $actor, string $client, string $name, string $email, string $password, string $reason, ?bool $synthetic = null): array
    {
        $synthetic ??= config('training.environment') !== 'production';
        abort_if(config('training.environment') === 'production' && $synthetic, 422, 'Account classification is not supported.');
        $email = Str::lower(trim($email));
        Validator::make(compact('client', 'name', 'email'), ['client' => 'required|uuid', 'name' => 'required|string|max:120', 'email' => 'required|email|max:200'])->validate();

        return $this->mutation($actor, $client, $password, $reason, function ($actor) use ($client, $name, $email, $reason, $synthetic) {
            $matches = User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->get();
            abort_if($matches->count() > 1, 409, 'Account requires identity review.');
            $user = $matches->first();
            if ($user) {
                $this->eligible($user);
                abort_unless($user->local_password_enabled_at && $this->identity($user), 409, 'Account requires setup or identity review.');
                abort_if(app(ClientAccess::class)->membership($user, $client), 409, 'Account already has access.');

                return $this->issue($actor, $user, 'invite', $client, $reason);
            }
            $user = User::create(['organisation_id' => $client, 'environment' => config('training.environment'), 'name' => $name,
                'email' => $email, 'password' => Str::random(64), 'is_synthetic' => $synthetic, 'active' => true,
                'origin' => 'manual', 'sync_policy' => 'local_only', 'role' => 'member', 'sensitive_access' => false]);
            $this->prepare($user);

            return $this->issue($actor, $user, 'setup', $client, $reason);
        });
    }

    private function mayRecover(User $actor, User $user, ?string $client, string $purpose): void
    {
        $this->eligible($user);
        abort_if($actor->id === $user->id, 403);
        $access = app(ClientAccess::class);
        if (! $access->globalAdmin($actor)) {
            abort_unless($client && ! $access->globalAdmin($user), 403);
            if ($purpose === 'setup') {
                // A Client Admin may only reissue initial setup for an account they created.
                abort_unless(DB::table('local_account_links')->where('user_id', $user->id)->where('issuer_id', $actor->id)
                    ->where('organisation_id', $client)->where('purpose', 'setup')->exists(), 403);
            } else {
                abort_unless($access->membership($user, $client), 403);
            }
        }
        abort_unless($purpose === 'setup' ? ! $user->local_password_enabled_at : (bool) $user->local_password_enabled_at, 409);
        abort_unless($this->identity($user), 422, 'External accounts require separate identity review.');
    }

    public function recover(User $actor, string $target, ?string $client, string $purpose, string $password, string $reason): array
    {
        abort_unless(in_array($purpose, ['setup', 'reset'], true), 422);
        abort_unless($purpose !== 'setup' || $client, 422, 'Choose the initial client when reissuing setup.');

        return $this->mutation($actor, $client, $password, $reason, function ($actor) use ($target, $client, $purpose, $reason) {
            $user = User::whereKey($target)->lockForUpdate()->firstOrFail();
            $this->mayRecover($actor, $user, $client, $purpose);

            return $this->issue($actor, $user, $purpose, $client, $reason);
        });
    }

    public function bootstrap(User $user, string $operator, string $reason): array
    {
        $this->assertEnabled();
        Validator::make(compact('operator', 'reason'), ['operator' => 'required|string|max:160', 'reason' => 'required|string|min:8|max:2000'])->validate();

        return DB::transaction(function () use ($user, $operator, $reason) {
            app(PlatformAdministration::class)->lock();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->eligible($user);
            abort_unless($user->is_synthetic, 403, 'Operator bootstrap remains restricted to synthetic accounts.');
            abort_unless(app(ClientAccess::class)->globalAdmin($user) && ! $user->local_password_enabled_at, 403);
            $this->prepare($user);

            return $this->issue($user, $user, 'setup', null, $reason.' Operator: '.$operator, true);
        }, 3);
    }

    public function initializeProduction(string $name, string $email, string $operator, string $reason): array
    {
        $this->assertEnabled();
        abort_unless(app()->runningInConsole() && app()->environment('production')
            && config('training.localhost_production') && config('training.environment') === 'production'
            && config('database.default') === 'sqlite'
            && realpath(config('database.connections.sqlite.database')) === realpath(config('training.localhost_database'))
            && realpath(config('training.localhost_database')) !== false, 403);
        $email = Str::lower(trim($email));
        Validator::make(compact('name', 'email', 'operator', 'reason'), ['name' => 'required|string|max:120',
            'email' => 'required|email|max:200', 'operator' => 'required|string|max:160', 'reason' => 'required|string|min:8|max:2000'])->validate();

        return DB::transaction(function () use ($name, $email, $operator, $reason) {
            $platform = app(PlatformAdministration::class);
            $platform->lock();
            $marker = DB::table('platform_audit_events')->where('environment', 'production')
                ->where('action', 'local.production.initialized')->first();
            if ($marker) {
                $user = User::whereKey($marker->target_id)->lockForUpdate()->firstOrFail();
                abort_unless($user->email === $email && $user->name === $name && ! $user->local_password_enabled_at
                    && $platform->admins()->count() === 1 && app(ClientAccess::class)->globalAdmin($user), 409,
                    'Initial setup is no longer available.');
                $this->eligible($user);
            } else {
                // This is a fresh-database initializer, never a lost-admin recovery path.
                abort_if(User::exists() || DB::table('role_assignments')->exists() || Organisation::exists(), 409,
                    'Initial setup requires an empty database.');
                $user = User::create(['name' => $name, 'email' => $email, 'password' => Str::random(64),
                    'organisation_id' => null, 'environment' => 'production', 'is_synthetic' => false, 'active' => true,
                    'origin' => 'manual', 'sync_policy' => 'local_only', 'role' => 'admin', 'sensitive_access' => false]);
                $this->prepare($user);
                $platform->appoint(null, $user->id, $reason, $operator);
                $platform->audit(null, 'local.production.initialized', $user->id, $reason, [], $operator);
                $user->refresh();
            }

            return $this->issue($user, $user, 'setup', null, $reason.' Operator: '.$operator, true);
        }, 5);
    }

    public function inspect(string $raw, string $purpose): object
    {
        $this->assertEnabled();
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $raw), 410, 'Link is unavailable.');
        $link = DB::table('local_account_links')->where('token_hash', hash('sha256', $raw))->where('purpose', $purpose)
            ->where('environment', config('training.environment'))->first();
        abort_unless($link && ! $link->consumed_at && ! $link->revoked_at && now()->lt($link->expires_at), 410, 'Link is unavailable or expired. Request a new link.');

        return $link;
    }

    private function redeem(string $raw, string $purpose, callable $operation): void
    {
        try {
            $this->redeemTransaction($raw, $purpose, $operation);
        } catch (HttpException $error) {
            if ($error->getStatusCode() === 403) {
                $link = DB::table('local_account_links')->where('token_hash', hash('sha256', $raw))->first();
                if ($link) {
                    app(PlatformAdministration::class)->audit(auth()->user(), 'local.redemption.denied', $link->id,
                        'Link redemption authority or recipient check failed', [], 'local-link-redemption');
                }
            }
            throw $error;
        }
    }

    private function redeemTransaction(string $raw, string $purpose, callable $operation): void
    {
        DB::transaction(function () use ($raw, $purpose, $operation) {
            app(PlatformAdministration::class)->lock();
            $link = $this->inspect($raw, $purpose);
            if ($link->organisation_id) {
                Organisation::whereKey($link->organisation_id)->lockForUpdate()->firstOrFail();
            }
            $actor = User::whereKey($link->issuer_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($link->user_id)->lockForUpdate()->firstOrFail();
            DB::table('local_account_links')->where('id', $link->id)->lockForUpdate()->first();
            $this->eligible($user);
            $this->authorised($actor, $link->organisation_id);
            abort_unless($actor->permission_version === (int) $link->issuer_version, 403, 'Link authority changed. Request a new link.');
            if ($link->bootstrap) {
                abort_unless($actor->id === $user->id && app(ClientAccess::class)->globalAdmin($user) && ! $user->local_password_enabled_at, 403);
            } elseif ($purpose !== 'invite') {
                $this->mayRecover($actor, $user, $link->organisation_id, $purpose);
            }
            $operation($link, $actor, $user);
            DB::table('local_account_links')->where('id', $link->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, $purpose.'.consumed', $link->id, 'Recipient completed local account action');
        }, 3);
    }

    public function setPassword(string $raw, string $purpose, string $password, string $confirmation): void
    {
        $this->passwordLength($password);
        abort_unless(in_array($purpose, ['setup', 'reset'], true), 422);
        Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => 'required|string|min:15|max:128|confirmed'])->validate();
        $this->redeem($raw, $purpose, function ($link, $actor, $user) use ($password, $purpose) {
            abort_unless($this->identity($user), 403);
            $user->update(['password' => $password, 'local_password_enabled_at' => now()]);
            if ($purpose === 'setup' && $link->organisation_id) {
                $this->join($actor, $user, $link->organisation_id);
            }
            SensitiveAccess::invalidate([$user->id]);
            DB::table('local_account_links')->where('user_id', $user->id)->whereIn('purpose', ['setup', 'reset'])
                ->whereNull('consumed_at')->update(['revoked_at' => now()]);
        });
    }

    private function join(User $actor, User $user, string $client): void
    {
        abort_if(app(ClientAccess::class)->membership($user, $client), 409, 'Account already has access.');
        $service = app(ClientAdministration::class);
        $member = $service->join($actor, $client, $user->id, 'Recipient accepted local client access');
        $service->grantRole($actor, $client, $member->id, 'member', null, 'Recipient accepted Member role');
    }

    public function accept(User $recipient, string $raw): void
    {
        $this->redeem($raw, 'invite', function ($link, $actor, $user) use ($recipient) {
            abort_unless($recipient->id === $user->id && $user->local_password_enabled_at && $this->identity($user)
                && app(Access::class)->fresh($user), 403, 'Sign in as the intended recipient.');
            $this->join($actor, $user, $link->organisation_id);
        });
    }

    public function cancel(User $actor, string $id, ?string $client, string $password, string $reason): void
    {
        $this->mutation($actor, $client, $password, $reason, function ($actor) use ($id, $client, $reason) {
            $link = DB::table('local_account_links')->where('id', $id)->lockForUpdate()->first();
            abort_unless($link && $link->environment === config('training.environment') && ($link->issuer_id === $actor->id || app(ClientAccess::class)->globalAdmin($actor))
                && ($client === $link->organisation_id || app(ClientAccess::class)->globalAdmin($actor)), 403);
            DB::table('local_account_links')->where('id', $id)->whereNull('consumed_at')->update(['revoked_at' => now()]);
            $this->audit($actor, 'link.cancelled', $id, $reason);
        });
    }

    public function changePassword(User $user, string $current, string $password, string $confirmation): void
    {
        $this->passwordLength($password);
        $this->assertEnabled();
        Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => 'required|string|min:15|max:128|confirmed'])->validate();
        DB::transaction(function () use ($user, $current, $password) {
            app(PlatformAdministration::class)->lock();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->eligible($user);
            $this->password($user, $current);
            $user->update(['password' => $password]);
            SensitiveAccess::invalidate([$user->id]);
            DB::table('local_account_links')->where('user_id', $user->id)->whereIn('purpose', ['setup', 'reset'])->whereNull('consumed_at')->update(['revoked_at' => now()]);
            $this->audit($user, 'password.changed', $user->id, 'Account owner changed password');
        }, 3);
    }

    public function authenticate(string $email, string $password): User
    {
        $this->assertEnabled();
        $users = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->get();
        $user = $users->count() === 1 ? $users->first() : null;
        // Fixed valid hash keeps missing-account checks on the password hashing path.
        $hash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = Hash::check($password, $hash);
        if (! $valid || strlen($password) > 72 || ! $user || ! $user->local_password_enabled_at
            || ! $this->identity($user) || ! app(Access::class)->active($user) || ! app(Access::class)->fresh($user)) {
            throw ValidationException::withMessages(['email' => 'Sign-in failed. Check your details or contact your administrator.']);
        }

        return $user;
    }

    private function passwordLength(string $password): void
    {
        // Bcrypt truncates beyond 72 bytes; never accept an indistinguishable suffix.
        if (strlen($password) > 72) {
            throw ValidationException::withMessages(['password' => 'Use a password of at most 72 UTF-8 bytes.']);
        }
    }
}
