<?php

declare(strict_types=1);

namespace Foundation\Common\Policies;

use BackedEnum;
use Foundation\Common\Contracts\Auth\PermissionSource;
use Foundation\Common\Contracts\Auth\Principal;

/** Base of every module's policies: a gesture is allowed when the user holds its permission. */
abstract class Policy
{
    public function __construct(protected readonly PermissionSource $permissions) {}

    protected function allows(mixed $user, BackedEnum $permission): bool
    {
        return $user instanceof Principal && in_array((string) $permission->value, $this->permissions->grants($user), true);
    }
}
