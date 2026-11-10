<?php

namespace App\Services\Installation;

use RuntimeException;

class InstallationState
{
    public function directory(): string
    {
        return storage_path('app/installation');
    }

    public function read(): ?array
    {
        $path = $this->directory().'/state.json';
        if (! is_file($path)) {
            return null;
        }
        $value = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($value) || ! isset($value['id'], $value['status'], $value['key'])) {
            throw new RuntimeException('Installation state is damaged. Restore the installation backup.');
        }

        return $value;
    }

    public function locked(callable $operation): mixed
    {
        if (! is_dir($this->directory()) && ! mkdir($this->directory(), 0700, true)) {
            throw new RuntimeException('Installation storage is not writable.');
        }
        $handle = fopen($this->directory().'/installation.lock', 'c');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another installation request is running. Wait, then retry.');
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function write(array $state): void
    {
        $this->atomic($this->directory().'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function atomic(string $path, string $contents): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write installation configuration. Check directory permissions.');
        }
        chmod($temporary, 0600);
        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Cannot replace installation configuration. Restore the saved backup if necessary.');
        }
    }

    public function fresh(): bool
    {
        if (config('installation.status') === 'installed' || app()->configurationIsCached()) {
            return false;
        }
        $state = $this->read();
        if ($state) {
            return $state['status'] !== 'installed';
        }
        // Existing keys and legacy files require explicit operator review.
        // The selected server database is checked for emptiness before any write.
        if (config('app.key')) {
            return false;
        }
        if (is_file(base_path('.env')) && config('installation.status') !== 'pending') {
            return false;
        }
        if (glob(storage_path('app/installations/*'))
            || is_file($this->directory().'/environment-before-install.env')
            || is_file($this->directory().'/environment-before-upgrade.env')
            || is_dir($this->directory().'/sessions')) {
            return false;
        }
        foreach ([database_path('database.sqlite'), base_path('.runtime/local-production.sqlite'), base_path('.runtime/m2-browser.sqlite')] as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return false;
            }
        }

        return true;
    }

    public function initialize(string $mode): array
    {
        if (! in_array($mode, ['local', 'hosted'], true)) {
            throw new RuntimeException('Installation mode must be local or hosted.');
        }

        return $this->locked(function () use ($mode) {
            if (! $this->fresh()) {
                throw new RuntimeException('This installation is not fresh. Setup cannot reopen.');
            }
            if ($state = $this->read()) {
                if ($state['mode'] !== $mode) {
                    throw new RuntimeException('Installation mode does not match the server configuration.');
                }

                return $state;
            }
            $state = ['id' => bin2hex(random_bytes(16)), 'status' => 'pending', 'mode' => $mode,
                'key' => 'base64:'.base64_encode(random_bytes(32)), 'created_at' => gmdate('c')];
            $this->write($state);

            return $state;
        });
    }

    public function issueToken(): string
    {
        return $this->locked(function () {
            $state = $this->read();
            if (! $state || $state['status'] === 'installed') {
                throw new RuntimeException('There is no pending installation.');
            }
            $token = bin2hex(random_bytes(32));
            $state['token_hash'] = hash('sha256', $token);
            $state['token_expires'] = time() + 3600;
            unset($state['session_owner']);
            $this->write($state);

            return $token;
        });
    }
}
