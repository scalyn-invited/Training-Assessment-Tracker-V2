<?php

namespace App\Http\Controllers;

use App\Models\Identity;
use App\Services\Audit;
use App\Services\MockIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $mock->assertEnabled();
        $data = $request->validate(['identity_id' => 'required|uuid']);
        $identity = Identity::findOrFail($data['identity_id']);
        $user = $mock->resolve($identity->issuer, $identity->subject);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(['identity_id' => $identity->id, 'login_at' => time(), 'last_seen' => time(), 'permission_version' => $user->permission_version]);
        Audit::record($user, 'mock.login', $identity->id);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
