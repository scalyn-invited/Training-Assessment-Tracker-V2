<?php

namespace App\Services\Ai;

final class ProviderResult
{
    public function __construct(public string $text, public int $inputTokens, public int $outputTokens, public ?string $externalId = null, public bool $refused = false) {}
}
