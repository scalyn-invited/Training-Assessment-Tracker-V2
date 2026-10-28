<?php

namespace App\Http\Middleware;

use App\Models\Identity;
use App\Services\Access;
use App\Services\LocalAccounts;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\PlatformAdministration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrainingSession
{
    public function handle(Request $request, Closure $next, string $scope = 'client')
    {
        $user = $request->user();
        $user = $user?->fresh();
        $identityActive = Identity::whereKey($request->session()->get('identity_id'))->where('user_id', $user?->id)->where('active', true)->exists();
        if (config('training.authentication_mode') === 'local_password') {
            $identityActive = $identityActive && app(LocalAccounts::class)->enabled()
                && $user?->local_password_enabled_at
                && app(LocalAccounts::class)->identity($user)?->id === $request->session()->get('identity_id');
        }
        if (! $user || ! $identityActive || ! app(Access::class)->active($user)
            || $request->session()->get('permission_version') !== $user->permission_version
            || time() - $request->session()->get('login_at', 0) >= config('training.session_absolute_seconds')
            || time() - $request->session()->get('last_seen', 0) >= config('training.session_idle_seconds')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Please sign in with an active identity.');
        }
        abort_unless(app(Access::class)->fresh($user), 503, 'Permissions are stale. Refresh directory access.');
        $request->session()->put('last_seen', time());
        $context = app(ClientContext::class);
        $context->id = null;
        if ($scope === 'preclient') {
            return $next($request);
        }
        if ($request->routeIs('platform.*')) {
            app(PlatformAdministration::class)->authorised($user);
        } elseif (! $request->routeIs('clients.*')) {
            $choices = $context->choices($user);
            $selected = $request->session()->get('client_id');
            if (! $selected && $choices->count() === 1) {
                $selected = $choices->first()->id;
                $request->session()->put('client_id', $selected);
            }
            if (! $selected || ! $choices->contains('id', $selected)) {
                $request->session()->forget('client_id');

                return $request->expectsJson() ? response()->json(['message' => 'Choose an authorised client.'], 409) : redirect()->route('clients.show');
            }
            $context->select($user, $selected);
        }

        return $next($request);
    }
}
