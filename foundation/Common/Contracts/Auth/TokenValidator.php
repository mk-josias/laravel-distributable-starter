<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Auth;

use Foundation\Common\Auth\Identity;

/** Turns the token of a request into the identity it proves, or null when it proves nothing. */
interface TokenValidator
{
    public function validate(string $token): ?Identity;
}
