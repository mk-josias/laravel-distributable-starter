<?php

declare(strict_types=1);

namespace Foundation\Iam\Auth;

use Foundation\Common\Contracts\Auth\PermissionSource;
use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Iam\Services\IamClient;

/** iam holds the roles and permissions: every module asks it, through its client. */
final readonly class RpcPermissions implements PermissionSource
{
    public function __construct(private IamClient $iam) {}

    public function grants(Principal $principal): array
    {
        return $this->iam->grants((int) $principal->id());
    }
}
