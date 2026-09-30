<?php

namespace App\Services;

use App\Models\Identity;
use App\Models\User;
use Illuminate\Support\Str;

// Local contract harness only. This is NOT an OIDC provider, MFA implementation or production JWT verifier.
class MockIdentity
{
    public function enabled(): bool
    {
        return app()->environment(['local', 'testing']) && config('training.mock_identity') && config('training.environment') === 'test';
    }

    public function assertEnabled(): void
    {
        abort_unless($this->enabled(), 503, 'Live identity integration is not configured.');
    }

    public function resolve(string $issuer, string $subject): User
    {
        $identity = Identity::where(compact('issuer', 'subject'))->where('active', true)->first();
        abort_unless($identity, 401);
        $user = User::findOrFail($identity->user_id);
        abort_unless($user->is_synthetic && $user->environment === 'test' && app(Access::class)->active($user), 401);

        return $user;
    }

    public function issue(User $user, array $overrides = []): string
    {
        $this->assertEnabled();
        $identity = Identity::where('user_id', $user->id)->firstOrFail();
        $claims = array_replace([
            'iss' => $identity->issuer, 'sub' => $identity->subject, 'aud' => config('training.mock_audience'),
            'client_id' => config('training.mock_client'), 'kind' => 'delegated',
            'scope' => ['training.self.read', 'training.member.read', 'training.group.read', 'jobs.read'],
            'iat' => time(), 'exp' => time() + 300, 'jti' => (string) Str::uuid(),
            'permission_version' => $user->permission_version,
        ], $overrides);
        $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, config('app.key'));
    }

    public function verify(string $token, string $scope): User
    {
        $this->assertEnabled();
        abort_if(strlen($token) > 8192, 401);
        $parts = explode('.', $token);
        abort_unless(count($parts) === 2 && hash_equals(hash_hmac('sha256', $parts[0], config('app.key')), $parts[1]), 401);
        $claims = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true) ?: '', true);
        abort_unless(is_array($claims) && ($claims['iss'] ?? null) === config('training.mock_issuer')
            && ($claims['aud'] ?? null) === config('training.mock_audience')
            && ($claims['client_id'] ?? null) === config('training.mock_client')
            && ($claims['kind'] ?? null) === 'delegated'
            && is_int($claims['exp'] ?? null) && is_int($claims['iat'] ?? null)
            && $claims['exp'] > time() && $claims['iat'] <= time() && $claims['exp'] - $claims['iat'] <= 300
            && is_string($claims['sub'] ?? null), 401);
        abort_unless(is_array($claims['scope'] ?? null) && in_array($scope, $claims['scope'], true), 403);
        $user = $this->resolve($claims['iss'], $claims['sub']);
        abort_unless(($claims['permission_version'] ?? null) === $user->permission_version, 401);
        abort_unless(app(Access::class)->fresh($user), 503, 'Permissions are stale.');

        return $user;
    }
}
