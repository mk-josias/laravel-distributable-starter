<?php

declare(strict_types=1);

namespace Foundation\Common\Auth;

use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Common\Contracts\Auth\PrincipalResolver;

/** For an identity provider outside the application: the token is all the module knows of the user. */
final readonly class ClaimsPrincipals implements PrincipalResolver
{
    public function resolve(Identity $identity): Principal
    {
        return new ClaimsPrincipal($identity);
    }
}
