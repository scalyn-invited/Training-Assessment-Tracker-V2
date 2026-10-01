<?php

namespace App\Services\Ai;

final class ProviderFailure extends \RuntimeException
{
    public function __construct(public string $kind)
    {
        // Never persist upstream bodies or exception strings containing credentials/input.
        parent::__construct($kind);
    }
}
