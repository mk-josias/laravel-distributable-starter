<?php

declare(strict_types=1);

namespace Foundation\Iam\Shadows;

use Distributable\Contracts\PublishesMigrations;
use Foundation\Common\Auth\IsPrincipal;
use Foundation\Common\Auth\Principal;
use Microservices\Models\ShadowModel;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
class UserShadow extends ShadowModel implements Principal, PublishesMigrations
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
