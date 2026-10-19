<?php

declare(strict_types=1);

namespace Foundation\Iam\Shadows;

use Distributable\Models\ShadowModel;
use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Common\Traits\IsPrincipal;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
class UserShadow extends ShadowModel implements Principal
{
    use IsPrincipal;

    public static function owner(): string
    {
        return 'iam';
    }

    public static function sourceTable(): string
    {
        return 'iam_users';
    }

    public static function migrations(): array
    {
        return [__DIR__.'/../database/shadows/users'];
    }
}
