<?php

namespace App\Services;

use App\Models\User;
use App\Services\Tenancy\ClientContext;

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
        $mode = in_array($raw['mode'] ?? '', ['cerebru', 'co-branded', 'white-label'], true) ? $raw['mode'] : 'cerebru';
        $clientName = $this->text($raw['client_name'] ?? '', '');
        $app = $this->text($raw['app_name'] ?? '', 'Assessment Training Tracker');
        $theme = array_key_exists($raw['default_theme'] ?? '', self::THEMES) ? $raw['default_theme'] : 'dark';
        $light = $this->asset($raw['logo_light'] ?? null);
        $dark = $this->asset($raw['logo_dark'] ?? null);

        return [
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
            'themes' => self::THEMES,
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
