<?php

declare(strict_types=1);

namespace Foundation\Common\Auth;

use Foundation\Common\Contracts\Auth\PermissionSource;
use Foundation\Common\Contracts\Auth\Principal;

/** The issuer wrote the permissions in the token, under the claim auth.permissions_claim names. */
final readonly class ClaimsPermissions implements PermissionSource
{
    public function grants(Principal $principal): array
    {
        $granted = $principal instanceof ClaimsPrincipal ? $principal->claim((string) config('auth.permissions_claim')) : null;

        return is_array($granted) ? array_values(array_map(strval(...), $granted)) : [];
    }
}
