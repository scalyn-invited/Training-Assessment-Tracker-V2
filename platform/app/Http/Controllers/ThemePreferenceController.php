<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\Branding;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ThemePreferenceController extends Controller
{
    public function save(Request $request, ClientContext $context)
    {
        abort_unless($context->id && $context->choices($request->user())->contains('id', $context->id), 403);
        abort_unless(Schema::hasTable('client_theme_preferences'), 503, 'Theme preferences are not ready.');
        $data = $request->validate(['client_id' => 'required|in:'.$context->id, 'version' => 'required|integer|min:0', 'theme' => 'present|nullable|string']);

        return DB::transaction(function () use ($request, $context, $data) {
            Organisation::whereKey($context->id)->lockForUpdate()->firstOrFail();
            $user = $request->user()->fresh();
            abort_unless($user && app(ClientAccess::class)->active($user) && $context->choices($user)->contains('id', $context->id), 403);
            $branding = app(Branding::class)->forUser($user);
            $theme = $data['theme'];
            if ($theme === 'client' && isset($branding['themes']['client-light'])) {
                $theme = 'client-light';
            }
            abort_unless($theme === null || isset($branding['themes'][$theme]), 422, 'Choose an available theme.');
            $query = DB::table('client_theme_preferences')->where('user_id', $user->id)->where('organisation_id', $context->id)->where('environment', $user->environment);
            $old = $query->first();
            abort_unless((int) $data['version'] === (int) ($old->version ?? 0), 409, 'Your theme changed elsewhere. Reload before choosing again.');
            $values = ['theme' => $theme, 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                $query->update($values);
            } else {
                DB::table('client_theme_preferences')->insert($values + ['user_id' => $user->id, 'organisation_id' => $context->id, 'environment' => $user->environment, 'created_at' => now()]);
            }
            DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $context->id, 'environment' => $user->environment, 'actor_id' => $user->id, 'action' => 'client.theme.preference.saved', 'target_id' => $user->id, 'request_id' => (string) Str::uuid(), 'metadata' => json_encode(['before' => $old?->theme, 'after' => $theme, 'version' => $values['version']]), 'created_at' => now()]);

            return response()->json(['theme' => $theme ?? $branding['default_theme'], 'version' => $values['version']]);
        }, 3);
    }
}
