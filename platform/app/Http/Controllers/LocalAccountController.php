<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\User;
use App\Services\LocalAccountDelivery;
use App\Services\LocalAccounts;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LocalAccountController extends Controller
{
    public function actions(Request $request, LocalAccounts $accounts)
    {
        $data = $request->validate(['action' => 'required|in:create,setup,reset,cancel', 'client' => 'required_if:action,create,setup|nullable|uuid',
            'name' => 'nullable|string|max:120', 'email' => 'nullable|email|max:200', 'target' => 'required_if:action,setup,reset|nullable|uuid',
            'link' => 'nullable|uuid', 'is_synthetic' => config('training.environment') === 'production' ? 'prohibited' : 'sometimes|boolean', 'send_email' => 'sometimes|boolean',
            'current_password' => 'required|string|max:128', 'reason' => 'required|string|min:8|max:2000']);
        $actor = $request->user();
        $client = $data['client'] ?? null;
        if (! app(ClientAccess::class)->globalAdmin($actor)) {
            abort_unless($client && $request->session()->get('client_id') === $client, 403);
        }
        $result = match ($data['action']) {
            'create' => $accounts->create($actor, $client ?? '', $data['name'] ?? '', $data['email'] ?? '', $data['current_password'], $data['reason'], isset($data['is_synthetic']) ? (bool) $data['is_synthetic'] : null),
            'setup', 'reset' => $accounts->recover($actor, $data['target'] ?? '', $client, $data['action'], $data['current_password'], $data['reason']),
            'cancel' => $accounts->cancel($actor, $data['link'] ?? '', $client, $data['current_password'], $data['reason']),
        };

        $delivery = $result && $request->boolean('send_email')
            ? app(LocalAccountDelivery::class)->send($actor, $result) : null;

        return response()->view('local-accounts.result', compact('result', 'delivery'));
    }

    public function links(Request $request, LocalAccounts $accounts)
    {
        $accounts->assertEnabled();
        $actor = $request->user();
        $global = app(ClientAccess::class)->globalAdmin($actor);
        $client = $global ? $request->query('client') : $request->session()->get('client_id');
        abort_unless($global || ($client && app(ClientAccess::class)->administer($actor, $client)), 403);
        $links = DB::table('local_account_links')->where('environment', config('training.environment'))
            ->when(! $global, fn ($q) => $q->where('issuer_id', $actor->id)->where('organisation_id', $client))
            ->when($global && $client, fn ($q) => $q->where('organisation_id', $client))->orderByDesc('created_at')->paginate(20);
        $targets = User::where('environment', config('training.environment'))->where('active', true)
            ->when(config('training.environment') === 'production', fn ($q) => $q->where('is_synthetic', false))
            ->where('id', '!=', $actor->id)
            ->when(! $global, fn ($q) => $q->where(function ($q) use ($client, $actor) {
                $q->whereIn('id', app(ClientAccess::class)->activeMemberships()->where('cm.organisation_id', $client)->select('cm.user_id'))
                    ->orWhereIn('id', DB::table('local_account_links')->where('issuer_id', $actor->id)->where('organisation_id', $client)->where('purpose', 'setup')->select('user_id'));
            }))->orderBy('name')->get()->filter(fn ($user) => $accounts->identity($user)
                && ($global || ! app(ClientAccess::class)->globalAdmin($user)));

        $clientChoices = $global ? app(PlatformAdministration::class)->clients()->where('active', true)->orderBy('name')->get()
            ->filter(fn ($entry) => app(ClientAccess::class)->available($entry->id)) : collect();

        return view('local-accounts.manage', compact('links', 'client', 'global', 'targets', 'clientChoices'));
    }

    public function passwordForm(Request $request, LocalAccounts $accounts, string $purpose, string $token)
    {
        abort_unless(in_array($purpose, ['setup', 'reset'], true), 404);
        $accounts->inspect($token, $purpose);

        return view('local-accounts.password', compact('purpose', 'token'));
    }

    public function passwordSave(Request $request, LocalAccounts $accounts, string $purpose, string $token)
    {
        $data = $request->validate(['password' => 'required|string|min:15|max:128|confirmed']);
        $accounts->setPassword($token, $purpose, $data['password'], $request->string('password_confirmation')->toString());
        $this->logout($request);

        return redirect('/login')->with('status', 'Password saved. Sign in with your new password.');
    }

    public function change(Request $request, LocalAccounts $accounts)
    {
        if ($request->isMethod('get')) {
            $accounts->assertEnabled();

            return view('local-accounts.password', ['purpose' => 'change', 'token' => null]);
        }
        $data = $request->validate(['current_password' => 'required|string|max:128', 'password' => 'required|string|min:15|max:128|confirmed']);
        $accounts->changePassword($request->user(), $data['current_password'], $data['password'], $request->string('password_confirmation')->toString());
        $this->logout($request);

        return redirect('/login')->with('status', 'Password changed. All sessions have ended.');
    }

    public function invitation(Request $request, LocalAccounts $accounts, string $token)
    {
        $link = $accounts->inspect($token, 'invite');
        abort_unless($link->user_id === $request->user()->id, 403, 'Sign in as the intended recipient.');
        $client = Organisation::findOrFail($link->organisation_id);

        return view('local-accounts.invitation', compact('token', 'client'));
    }

    public function accept(Request $request, LocalAccounts $accounts, string $token)
    {
        $request->validate(['accept' => 'accepted']);
        $accounts->accept($request->user(), $token);
        $this->logout($request);

        return redirect('/login')->with('status', 'Invitation accepted. Sign in again to choose your client.');
    }

    private function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        app(ClientContext::class)->id = null;
    }
}
