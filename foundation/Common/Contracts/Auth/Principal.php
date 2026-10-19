<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Auth;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/** The user of a request: the guard user (Authenticatable) the policies are asked about (Authorizable). */
interface Principal extends Authenticatable, Authorizable
{
    public function id(): int|string;
}
