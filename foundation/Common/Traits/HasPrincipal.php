<?php

declare(strict_types=1);

namespace Foundation\Common\Traits;

use Foundation\Common\Contracts\Auth\Principal;
use Illuminate\Auth\AuthenticationException;

trait HasPrincipal
{
    /** The principal the Authenticate middleware bound, on every surface. */
    protected function principal(): ?Principal
    {
        $user = request()->user();

        return $user instanceof Principal ? $user : null;
    }

    protected function principalId(): int|string|null
    {
        return $this->principal()?->id();
    }

    /** For a query scoped to the caller: no principal is a 401, never an id the scope would read as someone. */
    protected function principalIdOrFail(): int|string
    {
        return $this->principalId() ?? throw new AuthenticationException;
    }
}
