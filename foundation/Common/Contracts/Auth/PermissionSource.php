<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Auth;

interface PermissionSource
{
    /** @return list<string> the permissions the user holds */
    public function grants(Principal $principal): array;
}
