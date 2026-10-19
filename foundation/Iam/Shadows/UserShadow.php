<?php

declare(strict_types=1);

namespace Foundation\Iam\Shadows;

use Distributable\Attributes\Shadow;
use Distributable\Models\ShadowModel;
use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Common\Traits\IsPrincipal;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
#[Shadow(module: 'iam', table: 'iam_users', migrations: [__DIR__.'/../database/shadows/users'])]
class UserShadow extends ShadowModel implements Principal
{
    use IsPrincipal;
}
