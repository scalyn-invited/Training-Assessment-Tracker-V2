<?php

namespace App\Http\Controllers;

use App\Models\Identity;
use App\Services\Access;
use App\Services\Audit;
use App\Services\LocalAccounts;
use App\Services\MockIdentity;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SessionController
{
    public function create(MockIdentity $mock)
    {
        $identities = $mock->enabled() ? Identity::where('active', true)->get()->filter(function ($identity) use ($mock) {
            try {
                $mock->resolve($identity->issuer, $identity->subject);

                return true;
            } catch (\Throwable) {
                return false;
            }
        }) : collect();

        return view('login', compact('identities'));
    }

    public function store(Request $request, MockIdentity $mock)
    {
        if (config('training.authentication_mode') === 'local_password') {
            $data = $request->validate(['email' => 'required|string|max:200', 'password' => 'required|string|max:128']);
            $key = 'local-login:'.hash('sha256', Str::lower(trim($data['email'])));
            abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Please wait before trying again.');
            RateLimiter::hit($key, 60);
            $accounts = app(LocalAccounts::class);
            $user = $accounts->authenticate($data['email'], $data['password']);
            RateLimiter::clear($key);
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put(['identity_id' => $accounts->identity($user)->id, 'login_at' => time(), 'last_seen' => time(), 'permission_version' => $user->permission_version]);
            $request->session()->forget('client_id');
            app(ClientContext::class)->id = null;
            app(PlatformAdministration::class)->audit($user, 'local.login', $user->id, 'Local password sign-in');

            return redirect()->route(app(ClientAccess::class)->globalAdmin($user) ? 'platform.overview' : 'clients.show');
        }
        $mock->assertEnabled();
        $data = $request->validate(['identity_id' => 'required|uuid']);
        $identity = Identity::findOrFail($data['identity_id']);
        $user = $mock->resolve($identity->issuer, $identity->subject);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(['identity_id' => $identity->id, 'login_at' => time(), 'last_seen' => time(), 'permission_version' => $user->permission_version]);
        app(ClientContext::class)->id = null;
        $request->session()->forget('client_id');
        if (app(Access::class)->client($user)) {
            Audit::record($user, 'mock.login', $identity->id);
        }

        return redirect()->route(app(ClientAccess::class)->globalAdmin($user) ? 'platform.overview' : 'dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
