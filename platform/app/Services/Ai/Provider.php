<?php

namespace App\Services\Ai;

interface Provider
{
    public function generate(array $configuration, array $input): ProviderResult;
}
