<?php

namespace App\Services;

use App\Models\User;
use App\Services\Tenancy\ClientContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Branding
{
    public const THEMES = ['light' => 'Light', 'dark' => 'Dark', 'neon-cyan' => 'Neon Cyan', 'neon-emerald' => 'Neon Emerald', 'neon-amber' => 'Neon Amber'];

    public function forUser(?User $user): array
    {
        $client = $user && $user->active ? app(ClientContext::class)->resolve($user) : null;
        $defaults = config('branding.defaults', []);
        $overrides = $client !== null
            ? (config('branding.organisations', [])[$client][$user->environment] ?? []) : [];
        $raw = array_replace($defaults, $overrides);
        $saved = $client !== null && Schema::hasTable('client_brandings') ? DB::table('client_brandings')
            ->where('organisation_id', $client)->where('environment', $user->environment)->first() : null;
        if ($saved) {
            $raw = array_replace($raw, ['mode' => $saved->mode, 'client_name' => $saved->client_name,
                'app_name' => $saved->app_name, 'accent_light' => $saved->accent, 'accent_dark' => $saved->accent]);
        }
        $mode = in_array($raw['mode'] ?? '', ['cerebru', 'co-branded', 'white-label'], true) ? $raw['mode'] : 'cerebru';
        $clientName = $this->text($raw['client_name'] ?? '', '');
        $app = $this->text($raw['app_name'] ?? '', 'Assessment Training Tracker');
        $theme = array_key_exists($raw['default_theme'] ?? '', self::THEMES) ? $raw['default_theme'] : 'dark';
        $light = $this->asset($raw['logo_light'] ?? null);
        $dark = $this->asset($raw['logo_dark'] ?? null);
        $customLogo = $saved?->logo_path ? route('branding.logo', ['client' => $client, 'v' => $saved->version], false) : null;
        if ($customLogo) {
            $light = $dark = $customLogo;
        }

        $clientTheme = app(ClientTheme::class)->stored($saved->theme_colours ?? null);

        $generated = isset($clientTheme['generation']);
        $source = $clientTheme['source'] ?? $clientTheme['primary'] ?? $this->colour($raw['accent_dark'] ?? null, '#1d4668');
        $themeTokens = [];
        if ($generated) {
            foreach (app(ClientTheme::class)->generate($source) as $variant => $tokens) {
                foreach ($tokens as $key => $value) {
                    $themeTokens['client-'.$variant.'-'.$key] = $value;
                }
            }
        } elseif ($clientTheme) {
            foreach (app(ClientTheme::class)->tokens($clientTheme) as $key => $value) {
                $themeTokens['client-'.$key] = $value;
            }
        }

        $themes = self::THEMES + ($generated ? ['client-light' => 'Client Light', 'client-dark' => 'Client Dark'] : ($clientTheme ? ['client' => 'Client theme'] : []));
        $resolve = fn ($value) => $value === 'client' && $generated ? 'client-light' : $value;
        $savedDefault = $resolve($saved->default_theme ?? null);
        if (isset($themes[$savedDefault ?? ''])) {
            $theme = $savedDefault;
        }
        $preferencesReady = $client !== null && Schema::hasTable('client_theme_preferences');
        $preference = $preferencesReady ? DB::table('client_theme_preferences')->where('user_id', $user->id)->where('organisation_id', $client)->where('environment', $user->environment)->first() : null;
        $personal = $resolve($preference?->theme);

        return [
            'selected_theme' => isset($themes[$personal ?? '']) ? $personal : $theme,
            'saved_default_theme' => $savedDefault,
            'preference_ready' => $preferencesReady,
            'preference_client' => $client,
            'preference_version' => $preference->version ?? 0,
            'preference_decided' => $preference !== null,
            'theme_kind' => $generated ? 'generated' : ($clientTheme ? 'legacy' : 'none'),
            'brand_source' => $source,
            'theme_previews' => app(ClientTheme::class)->generate($source),
            'client_theme' => $clientTheme,
            'theme_tokens' => $themeTokens,
            'version' => $saved->version ?? 0,
            'custom_logo' => $customLogo,
            'mode' => $mode,
            'app_name' => $app,
            'client_name' => $clientName,
            'brand_name' => $mode === 'cerebru' ? 'Cerebru' : ($clientName ?: $app),
            'logo_light' => $light ?: $dark,
            'logo_dark' => $dark ?: $light,
            'compact_logo' => $this->asset($raw['compact_logo'] ?? null),
            'favicon' => $this->asset($raw['favicon'] ?? null) ?: '/images/training-mark.svg',
            'accent_light' => $this->colour($raw['accent_light'] ?? null, '#155e75'),
            'accent_dark' => $this->colour($raw['accent_dark'] ?? null, '#67e8f9'),
            'default_theme' => $theme,
            'footer' => $this->text($raw['footer'] ?? '', 'Learning with purpose. Progress with evidence.'),
            'cerebru_url' => $mode === 'white-label' ? null : $this->url($raw['cerebru_url'] ?? null),
            'theme_key' => 'training.appearance.'.hash('sha256', $user && $user->active ? ($client ?? 'unselected').'|'.$user->environment.'|'.$user->id : 'guest'),
            'themes' => self::THEMES + ($generated ? ['client-light' => 'Client Light', 'client-dark' => 'Client Dark'] : ($clientTheme ? ['client' => 'Client theme'] : [])),
        ];
    }

    private function text(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 120) : $fallback;
    }

    private function colour(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? $value : $fallback;
    }

    private function asset(mixed $value): ?string
    {
        // Only curated same-origin raster/SVG assets under public/images. No arbitrary upload or remote tracking URL.
        return is_string($value) && preg_match('~^/images/[a-zA-Z0-9_/-]+\.(png|jpe?g|webp|svg)$~D', $value)
            && is_file(public_path(ltrim($value, '/'))) ? $value : null;
    }

    private function url(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
            && parse_url($value, PHP_URL_SCHEME) === 'https' && ! parse_url($value, PHP_URL_USER)
            && ! parse_url($value, PHP_URL_PASS) ? $value : null;
    }
}
