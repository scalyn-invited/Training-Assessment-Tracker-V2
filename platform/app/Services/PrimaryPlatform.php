<?php

namespace App\Services;

// Implement this boundary only after the actual platform contract is supplied.
// Tests replace it with an isolated adapter. No guessed endpoint or machine-token delegation.
class PrimaryPlatform
{
    public function createPerson(string $key, array $payload): array
    {
        throw new \RuntimeException('primary_not_configured');
    }

    public function findByKey(string $key): ?array
    {
        throw new \RuntimeException('primary_not_configured');
    }
}
