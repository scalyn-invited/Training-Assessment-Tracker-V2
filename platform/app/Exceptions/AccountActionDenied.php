<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Fixed, credential-free diagnostic text for authenticated account administration. */
class AccountActionDenied extends HttpException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct(403, $message);
    }
}
