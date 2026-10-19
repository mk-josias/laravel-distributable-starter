<?php

declare(strict_types=1);

namespace Foundation\Common\Auth;

use Foundation\Common\Contracts\Auth\Principal;
use Illuminate\Foundation\Auth\Access\Authorizable;

/** A user known only by its token: no row in the module's database. */
final class ClaimsPrincipal implements Principal
{
    use Authorizable;

    public function __construct(private readonly Identity $identity) {}

    public function id(): int|string
    {
        return $this->identity->id;
    }

    public function claim(string $name): mixed
    {
        return $this->identity->claims[$name] ?? null;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->identity->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
