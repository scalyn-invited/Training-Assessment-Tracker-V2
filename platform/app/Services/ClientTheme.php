<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ClientTheme
{
    // Initial editor values reuse the existing Light theme; they do not override saved preferences.
    public const DEFAULTS = ['primary' => '#1d4668', 'background' => '#f4f8fb', 'card' => '#ffffff', 'text' => '#203f59'];

    public function contrast(string $one, string $two): float
    {
        $a = $this->luminance($one);
        $b = $this->luminance($two);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private function luminance(string $colour): float
    {
        $channels = array_map(function ($hex) {
            $value = hexdec($hex) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($colour, 1), 2));

        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
    }

    public function validate(array $colours): array
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (! is_string($colours[$key] ?? null) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $colours[$key])) {
                throw ValidationException::withMessages(['theme_'.$key => 'Choose a valid six-digit hex colour for '.$key.'.']);
            }
        }
        $errors = [];
        foreach (['text', 'primary'] as $foreground) {
            foreach (['background', 'card'] as $surface) {
                if ($this->contrast($colours[$foreground], $colours[$surface]) < 4.5) {
                    $errors['theme_'.$foreground][] = ucfirst($foreground).' and '.$surface.' colours need at least 4.5:1 contrast. Adjust either colour before saving.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return array_map('strtolower', array_intersect_key($colours, self::DEFAULTS));
    }

    public function stored(?string $json): ?array
    {
        $colours = json_decode($json ?? '', true);
        if (! is_array($colours)) {
            return null;
        }
        if (array_key_exists('generation', $colours)) {
            return $colours['generation'] === 1 && is_string($colours['source'] ?? null)
                && preg_match('/^#[0-9a-fA-F]{6}$/D', $colours['source'])
                ? ['generation' => 1, 'source' => strtolower($colours['source'])] : null;
        }
        try {
            return $this->validate($colours);
        } catch (ValidationException) {
            return null;
        }
    }

    public function tokens(array $colours): array
    {
        return $colours + [
            'on-primary' => $this->contrast($colours['primary'], '#000000') >= $this->contrast($colours['primary'], '#ffffff') ? '#000000' : '#ffffff',
            'scheme' => $this->luminance($colours['background']) > 0.179 ? 'light' : 'dark',
        ];
    }

    public function generate(string $source): array
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/D', $source)) {
            throw ValidationException::withMessages(['preview_accent' => 'Enter a colour as #RRGGBB.']);
        }
        $palettes = [];
        foreach (['light', 'dark'] as $scheme) {
            $dark = $scheme === 'dark';
            $target = $dark ? '#ffffff' : '#000000';
            $background = $this->mix($source, $dark ? '#0b0f14' : '#ffffff', 96);
            $card = $dark ? $this->mix($source, '#18212b', 94) : '#ffffff';
            $hover = $this->mix($source, $dark ? '#25313f' : '#ffffff', $dark ? 90 : 94);
            $surfaces = [$background, $card, $hover];
            $primary = $this->readable($source, $surfaces, $target, 4.5);
            $palettes[$scheme] = [
                'primary' => $primary, 'background' => $background, 'card' => $card,
                'text' => $this->readable($this->mix($source, $target, 90), $surfaces, $target, 7),
                'muted' => $this->readable($this->mix($source, $target, 55), $surfaces, $target, 4.5),
                'decorative' => $this->mix($source, $dark ? '#36414b' : '#e4e9ee', 92),
                'line' => $this->readable($this->mix($source, $background, 65), $surfaces, $target, 3),
                'focus' => $primary, 'raised' => $card, 'hover' => $hover, 'soft' => $hover,
                'primary-hover' => $this->mix($primary, $target, 12),
                'on-primary' => $dark ? '#000000' : '#ffffff', 'scheme' => $scheme,
            ];
        }

        return $palettes;
    }

    private function mix(string $source, string $target, int $percent): string
    {
        $result = '#';
        for ($i = 1; $i < 7; $i += 2) {
            $value = (int) floor((hexdec(substr($source, $i, 2)) * (100 - $percent)
                + hexdec(substr($target, $i, 2)) * $percent) / 100 + 0.5);
            $result .= sprintf('%02x', $value);
        }

        return $result;
    }

    private function readable(string $source, array $surfaces, string $target, float $minimum): string
    {
        for ($percent = 0; $percent <= 100; $percent++) {
            $candidate = $this->mix($source, $target, $percent);
            if (min(array_map(fn ($surface) => $this->contrast($candidate, $surface), $surfaces)) >= $minimum) {
                return $candidate;
            }
        }
        throw new \LogicException('Generated surfaces do not support the required contrast.');
    }
}
