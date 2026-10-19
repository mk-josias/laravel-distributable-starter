<?php

declare(strict_types=1);

namespace Foundation\Iam\Auth;

use Foundation\Common\Auth\ClaimsPrincipal;
use Foundation\Common\Auth\Identity;
use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Common\Contracts\Auth\PrincipalResolver;
use Foundation\Iam\Services\IamClient;

/** The user as iam answers it, whatever proved the identity: no copy in the module, one call to iam (cached by its client). */
final readonly class RpcPrincipals implements PrincipalResolver
{
    public function __construct(private IamClient $iam) {}

    public function resolve(Identity $identity): ?Principal
    {
        $user = $this->iam->findUser((int) $identity->id);

        return $user === null ? null : new ClaimsPrincipal(new Identity($user['id'], $user));
    }
}
