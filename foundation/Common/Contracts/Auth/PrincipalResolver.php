<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Auth;

use Foundation\Common\Auth\Identity;

/** Turns a proven identity into the user of the request, or null when the module doesn't know it. */
interface PrincipalResolver
{
    public function resolve(Identity $identity): ?Principal;
}
