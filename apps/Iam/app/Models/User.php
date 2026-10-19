<?php

declare(strict_types=1);

namespace Apps\Iam\Models;

use Distributable\Attributes\ShadowFields;
use Distributable\Contracts\Shadows\Shadowed;
use Distributable\Traits\ShadowSource;
use Foundation\Common\Contracts\Auth\Principal;
use Foundation\Common\Traits\IsPrincipal;
use Foundation\Common\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[ShadowFields('name', 'email')]
final class User extends Model implements Principal, Shadowed
{
    use HasApiTokens;
    use HasRoles;
    use IsPrincipal;
    use Searchable;
    use ShadowSource;

    protected $table = 'iam_users';

    protected string $guard_name = 'web';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /** @return list<string> */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }
}
