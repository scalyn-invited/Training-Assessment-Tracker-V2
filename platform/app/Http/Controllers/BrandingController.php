<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\ClientTheme;
use App\Services\Tenancy\ClientAccess;
use App\Services\Tenancy\ClientContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BrandingController extends Controller
{
    public function save(Request $request, ClientContext $context, ClientAccess $access)
    {
        $client = $context->id;
        abort_unless($client && $access->administer($request->user(), $client), 403);
        if (! Schema::hasTable('client_brandings')) {
            throw ValidationException::withMessages(['branding' => 'Branding settings are not ready in this workspace. Contact your administrator.']);
        }
        $data = $request->validate([
            'client_id' => 'required|in:'.$client,
            'version' => 'required|integer|min:0',
            'preview_mode' => 'required|in:cerebru,co-branded,white-label',
            'preview_client' => 'required|string|max:120',
            'preview_name' => 'required|string|max:120',
            'preview_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=4096,max_height=4096',
            'default_theme' => 'sometimes|nullable|in:light,dark,neon-cyan,neon-emerald,neon-amber,client,client-light,client-dark',
            'remove_logo' => 'sometimes|boolean',
        ]);
        if ($request->hasAny(['theme_primary', 'theme_background', 'theme_card', 'theme_text'])) {
            throw ValidationException::withMessages(['branding' => 'This colour form is out of date. Reload Appearance and choose one brand colour.']);
        }
        if (! Schema::hasColumn('client_brandings', 'theme_colours')) {
            throw ValidationException::withMessages(['branding' => 'Client theme settings are not ready in this workspace. Contact your administrator.']);
        }
        $themeColours = ['generation' => 1, 'source' => strtolower($data['preview_accent'])];
        app(ClientTheme::class)->generate($themeColours['source']);
        if ($request->hasFile('logo') && $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => 'Choose either a replacement logo or removal.']);
        }
        $newPath = null;
        $oldPath = null;
        try {
            if ($file = $request->file('logo')) {
                if (! function_exists('imagecreatefromstring')) {
                    throw ValidationException::withMessages(['logo' => 'Logo processing is unavailable. Ask the administrator to enable PHP GD.']);
                }
                $image = @imagecreatefromstring($file->get());
                if (! $image) {
                    throw ValidationException::withMessages(['logo' => 'Upload a valid PNG, JPEG or WebP image.']);
                }
                imagesavealpha($image, true);
                ob_start();
                imagepng($image);
                $bytes = ob_get_clean();
                imagedestroy($image);
                $newPath = 'branding/'.$client.'/'.Str::uuid().'.png';
                if (! Storage::disk('local')->put($newPath, $bytes)) {
                    throw new \RuntimeException('Unable to store logo.');
                }
            }
            DB::transaction(function () use ($request, $access, $client, $data, $newPath, $themeColours, &$oldPath) {
                Organisation::whereKey($client)->lockForUpdate()->firstOrFail();
                abort_unless($access->administer($request->user(), $client), 403);
                $query = DB::table('client_brandings')->where('organisation_id', $client)->where('environment', config('training.environment'));
                $old = $query->first();
                if ((int) $data['version'] !== (int) ($old->version ?? 0)) {
                    throw ValidationException::withMessages(['version' => 'Branding changed while you were editing. Reload Appearance before saving again.']);
                }
                $values = ['mode' => $data['preview_mode'], 'client_name' => trim($data['preview_client']),
                    'app_name' => trim($data['preview_name']), 'accent' => $data['preview_accent'],
                    'logo_path' => $request->boolean('remove_logo') ? null : ($newPath ?? $old?->logo_path),
                    'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
                if ($themeColours !== null) {
                    $values['theme_colours'] = json_encode($themeColours, JSON_THROW_ON_ERROR);
                }
                if (array_key_exists('default_theme', $data)) {
                    $values['default_theme'] = $data['default_theme'] === 'client' ? 'client-light' : $data['default_theme'];
                }
                $oldPath = $old?->logo_path;
                if ($old) {
                    $query->update($values);
                } else {
                    DB::table('client_brandings')->insert($values + ['organisation_id' => $client,
                        'environment' => config('training.environment'), 'created_at' => now()]);
                }
                $changes = [];
                foreach (array_diff(array_keys($values), ['version', 'updated_at']) as $field) {
                    if (($old?->$field) !== $values[$field]) {
                        $changes[$field] = ['before' => $old?->$field, 'after' => $values[$field]];
                    }
                }
                DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $client,
                    'environment' => config('training.environment'), 'actor_id' => $request->user()->id,
                    'action' => 'client.branding.saved', 'target_id' => $client, 'request_id' => (string) Str::uuid(),
                    'metadata' => json_encode(['changes' => $changes, 'version' => $values['version']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
                if ($oldPath === $values['logo_path']) {
                    $oldPath = null;
                }
            }, 3);
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            if ($error instanceof ValidationException || $error instanceof HttpExceptionInterface) {
                throw $error;
            }
            report($error);
            throw ValidationException::withMessages(['branding' => 'Branding could not be saved. Your previous settings are unchanged. Please try again.']);
        }
        // Database publication precedes cleanup; cleanup failure must not undo a successful save.
        if ($oldPath) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        return redirect()->route('appearance')->with('status', 'Client branding saved. Everyone in this client will see it on their next page load.');
    }

    public function logo(Request $request, string $client, ClientContext $context, ClientAccess $access)
    {
        abort_unless($context->id === $client && $access->active($request->user())
            && $context->choices($request->user())->contains('id', $client), 404);
        $path = DB::table('client_brandings')->where('organisation_id', $client)
            ->where('environment', config('training.environment'))->value('logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
