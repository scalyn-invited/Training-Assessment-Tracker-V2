<?php

namespace App\Http\Controllers;

use App\Services\Branding;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\PlatformAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PlatformController extends Controller
{
    public function show(Request $request, PlatformAdministration $service, string $section = 'overview')
    {
        $service->authorised($request->user());
        abort_unless(in_array($section, ['overview', 'clients', 'admins', 'appearance', 'audit']), 404);
        $env = $service->environment();
        $clientQuery = $service->clients();
        $clientCount = (clone $clientQuery)->count();
        $suspendedIds = DB::table('platform_client_states')->where('environment', $env)->where('suspended', true)->pluck('organisation_id');
        $clients = $clientQuery
            ->when($request->filled('client_search'), fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->string('client_search'), 0, 120).'%'))
            ->when($request->input('status') === 'suspended', fn ($q) => $q->whereIn('id', $suspendedIds))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('active', true)->whereNotIn('id', $suspendedIds))
            ->orderBy('name')->paginate(20)->withQueryString();
        $globalAdminIds = $service->admins()->pluck('ra.user_id');
        $activeAdminCount = $service->admins()->join('users as active_user', 'active_user.id', '=', 'ra.user_id')->where('active_user.active', true)->count();
        $clientChoices = $service->clients()->where('active', true)->orderBy('name')->get()
            ->filter(fn ($client) => app(ClientAccess::class)->available($client->id));
        $states = DB::table('platform_client_states')->where('environment', $env)->get()->keyBy('organisation_id');
        $accounts = $service->accounts()->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->string('search'), 0, 120).'%')->orWhere('email', 'like', '%'.mb_substr($request->string('search'), 0, 120).'%')))->orderBy('name')->paginate(20, ['id', 'name', 'email', 'permission_version'], 'accounts_page')->withQueryString();
        $accountClients = app(ClientAccess::class)->activeMemberships()->whereIn('cm.user_id', $accounts->pluck('id'))
            ->get(['cm.user_id', 'cm.organisation_id'])->groupBy('user_id');
        $accountRoles = DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->where('ra.environment', $env)->where('ra.scope_type', 'client')->whereNull('ra.ends_at')->where('ra.starts_at', '<=', now())
            ->whereIn('ra.user_id', $accounts->pluck('id'))->get(['ra.user_id', 'ra.organisation_id', 'r.code'])->groupBy('user_id');
        $membershipNames = $service->clients()->whereIn('id', $accountClients->flatten()->pluck('organisation_id'))->pluck('name', 'id');
        $admins = $service->admins()->join('users as u', 'u.id', '=', 'ra.user_id')->orderBy('u.name')->paginate(20, ['ra.id', 'u.name', 'u.email', 'u.active'], 'admins_page');
        $settings = DB::table('platform_settings')->where('environment', $env)->first();
        $clientAdmins = DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')->join('users as u', 'u.id', '=', 'ra.user_id')
            ->where('ra.environment', $env)->where('r.code', 'admin')->where('ra.scope_type', 'client')->whereNull('ra.ends_at')->where('ra.starts_at', '<=', now())
            ->whereIn('ra.organisation_id', $clients->pluck('id'))->get(['ra.organisation_id', 'u.name'])->groupBy('organisation_id');
        $events = DB::table('platform_audit_events as e')->leftJoin('users as u', 'u.id', '=', 'e.actor_id')->where('e.environment', $env)
            ->when($request->filled('query'), fn ($q) => $q->where(fn ($q) => $q->where('e.reason', 'like', '%'.mb_substr($request->string('query'), 0, 120).'%')->orWhere('e.target_id', 'like', '%'.mb_substr($request->string('query'), 0, 120).'%')))
            ->when($request->filled('action'), fn ($q) => $q->where('e.action', mb_substr($request->string('action'), 0, 100)))
            ->when($request->filled('target'), fn ($q) => $q->where('e.target_id', mb_substr($request->string('target'), 0, 80)))
            ->when($request->filled('actor'), fn ($q) => $q->where('u.name', 'like', '%'.mb_substr($request->string('actor'), 0, 120).'%'))
            ->when($request->filled('from') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->input('from')), fn ($q) => $q->whereDate('e.created_at', '>=', $request->input('from')))
            ->when($request->filled('to') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->input('to')), fn ($q) => $q->whereDate('e.created_at', '<=', $request->input('to')))
            ->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['e.created_at', 'e.action', 'e.target_id', 'e.reason', 'e.operator_reference', 'e.changes', 'u.name'], 'events_page')->withQueryString();

        $targetNames = DB::table('organisations')->where('environment', $env)->whereIn('id', $events->pluck('target_id'))->pluck('name', 'id')
            ->union(DB::table('users')->where('environment', $env)->whereIn('id', $events->pluck('target_id'))->pluck('name', 'id'));
        $auditActions = DB::table('platform_audit_events')->where('environment', $env)->distinct()->orderBy('action')->pluck('action');
        $events->getCollection()->transform(function ($event) use ($targetNames) {
            $raw = json_decode($event->changes ?? '{}', true);
            $safe = [];
            foreach (['name', 'suspended'] as $key) {
                foreach (['before', 'after'] as $side) {
                    $value = $raw[$side][$key] ?? null;
                    if (is_scalar($value)) {
                        $safe[$key][$side] = $value;
                    }
                }
            }
            foreach (['name', 'suspended', 'display_name', 'app_name', 'accent', 'default_theme', 'logo_changed'] as $key) {
                if (! is_array($raw) || ! array_key_exists($key, $raw)) {
                    continue;
                }
                $value = $raw[$key];
                if (is_array($value)) {
                    $safe[$key] = array_filter(array_intersect_key($value, array_flip(['before', 'after'])), fn ($v) => is_scalar($v) || $v === null);
                } elseif (is_scalar($value) || $value === null) {
                    $safe[$key] = $value;
                }
            }
            $event->changes = json_encode($safe);
            $event->target_name = $targetNames->get($event->target_id);

            return $event;
        });

        return view('platform', compact('section', 'clients', 'states', 'accounts', 'admins', 'settings', 'events', 'env', 'clientAdmins', 'clientChoices', 'accountClients', 'clientCount', 'globalAdminIds', 'activeAdminCount', 'auditActions', 'accountRoles', 'membershipNames'));
    }

    public function act(Request $request, PlatformAdministration $service)
    {
        try {
            return $this->perform($request, $service);
        } catch (ValidationException $error) {
            if ($request->expectsJson()) {
                throw $error;
            }

            $parameters = ['section' => in_array($request->input('action'), ['appoint', 'remove']) ? 'admins' : 'clients'];
            if ($request->filled('_accounts_page')) {
                $parameters['accounts_page'] = max(1, $request->integer('_accounts_page'));
            }
            if ($request->filled('_search')) {
                $parameters['search'] = mb_substr($request->string('_search')->toString(), 0, 120);
            }

            return redirect()->route('platform.section', $parameters)->withErrors($error->errors())
                ->withInput($request->only(['_form', 'user', 'client', 'action', 'reason', 'name', 'grant']));
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $error;
            }

            return response()->view('platform-conflict', ['message' => $error->getMessage()], 409);
        }
    }

    private function perform(Request $request, PlatformAdministration $service)
    {
        $service->authorised($request->user());
        $d = $request->validate(['action' => 'required|in:create,edit,suspend,reactivate,join,grant,revoke,appoint,remove',
            'client' => 'nullable|uuid', 'user' => 'nullable|uuid', 'grant' => 'nullable|uuid', 'name' => 'nullable|string|max:160',
            'version' => 'required|integer|min:0', 'reason' => 'required|string|min:8|max:2000']);
        $a = $d['action'];
        if (in_array($a, ['create', 'edit', 'suspend', 'reactivate'])) {
            if ($a !== 'create') {
                $request->validate(['client' => 'required|uuid']);
            }
            $service->client($request->user(), $a === 'create' ? null : $d['client'], $d['name'] ?? '', $d['version'], $d['reason'], match ($a) {
                'suspend' => true,'reactivate' => false,default => null
            });
        } elseif (in_array($a, ['join', 'grant', 'revoke'])) {
            $request->validate(['client' => 'required|uuid', 'user' => 'required|uuid']);
            $service->clientRole($request->user(), $d['client'], $d['user'], $a, $d['version'], $d['reason']);
        } elseif ($a === 'appoint') {
            $request->validate(['user' => 'required|uuid']);
            // Bind a grant to the account version displayed to this editor.
            DB::transaction(function () use ($service, $request, $d) {
                $service->lock();
                abort_unless($service->accounts()->whereKey($d['user'])->value('permission_version') === (int) $d['version'], 409, 'Account changed. Reload.');
                $service->appoint($request->user(), $d['user'], $d['reason']);
            }, 3);
        } else {
            $request->validate(['grant' => 'required|uuid']);
            $service->revoke($request->user(), $d['grant'], $d['reason']);
        }

        return redirect()->route('platform.section', in_array($a, ['appoint', 'remove']) ? 'admins' : 'clients')
            ->with('status', 'Platform administration updated.');
    }

    public function saveAppearance(Request $request, PlatformAdministration $service)
    {
        $service->authorised($request->user());
        $d = $request->validate(['version' => 'required|integer|min:0', 'display_name' => 'required|string|max:120',
            'app_name' => 'required|string|max:120', 'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'default_theme' => 'required|in:'.implode(',', array_keys(Branding::THEMES + Branding::PLATFORM_THEMES)), 'reason' => 'required|string|min:8|max:2000',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=4096,max_height=4096', 'remove_logo' => 'sometimes|boolean']);
        if ($request->hasFile('logo') && $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => 'Choose replacement or removal, not both.']);
        }
        $path = null;
        $obsolete = null;
        try {
            if ($file = $request->file('logo')) {
                if (! function_exists('imagecreatefromstring') || ! ($image = @imagecreatefromstring($file->get()))) {
                    throw ValidationException::withMessages(['logo' => 'Valid raster image processing is unavailable.']);
                }
                imagesavealpha($image, true);
                ob_start();
                imagepng($image);
                $bytes = ob_get_clean();
                imagedestroy($image);
                $path = 'platform-branding/'.$service->environment().'/'.Str::uuid().'.png';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new \RuntimeException('Logo storage failed.');
                }
            }
            DB::transaction(function () use ($request, $service, $d, $path, &$obsolete) {
                $old = $service->lock();
                $service->authorised($request->user());
                if ((int) $old->version !== (int) $d['version']) {
                    throw ValidationException::withMessages(['version' => 'Platform appearance changed. Reload before saving.']);
                }
                $values = array_intersect_key($d, array_flip(['display_name', 'app_name', 'accent', 'default_theme']));
                $values['logo_path'] = $request->boolean('remove_logo') ? null : ($path ?? $old->logo_path);
                $values['version'] = $old->version + 1;
                $values['updated_at'] = now();
                DB::table('platform_settings')->where('environment', $service->environment())->update($values);
                $changes = [];
                foreach (['display_name', 'app_name', 'accent', 'default_theme'] as $key) {
                    if ($values[$key] !== $old->$key) {
                        $changes[$key] = ['before' => $old->$key, 'after' => $values[$key]];
                    }
                }
                $changes['logo_changed'] = $old->logo_path !== $values['logo_path'];
                $service->audit($request->user(), 'platform.appearance.saved', $service->environment(), $d['reason'], $changes);
                if ($old->logo_path !== $values['logo_path']) {
                    $obsolete = $old->logo_path;
                }
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                throw $e;
            }
            report($e);
            throw ValidationException::withMessages(['logo' => 'Save failed. Previous platform settings remain unchanged.']);
        }
        if ($obsolete) {
            try {
                Storage::disk('local')->delete($obsolete);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('platform.section', 'appearance')->with('status', 'Platform appearance saved.');
    }

    public function logo()
    {
        $path = DB::table('platform_settings')->where('environment', config('training.environment'))->value('logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }
}
