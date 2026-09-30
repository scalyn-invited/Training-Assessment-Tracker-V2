<?php

namespace App\Http\Middleware;

use App\Models\Identity;
use App\Services\Access;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrainingSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $identityActive = Identity::whereKey($request->session()->get('identity_id'))->where('user_id', $user?->id)->where('active', true)->exists();
        if (! $user || ! $identityActive || ! app(Access::class)->active($user)
            || $request->session()->get('permission_version') !== $user->permission_version
            || time() - $request->session()->get('login_at', 0) >= config('training.session_absolute_seconds')
            || time() - $request->session()->get('last_seen', 0) >= config('training.session_idle_seconds')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Please sign in with an active identity.');
        }
        abort_if($user->role !== 'member' && ! app(Access::class)->fresh($user), 503, 'Permissions are stale. Refresh directory access.');
        $request->session()->put('last_seen', time());

        return $next($request);
    }
}
