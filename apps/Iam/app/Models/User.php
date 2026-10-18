<?php

declare(strict_types=1);

namespace Apps\Iam\Models;

use Foundation\Common\Auth\IsPrincipal;
use Foundation\Common\Auth\Principal;
use Foundation\Common\Database\Searchable;
use Foundation\Iam\Services\IamRpcService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Microservices\Contracts\Shadows\Shadowed;
use Microservices\Traits\ShadowSource;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
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

    /** @var list<string> the fields the other modules' copies carry */
    protected array $shadowed = ['name', 'email'];

    /** Once a change is committed, the cached answer of findUser() is forgotten: callers and the gateway read the new one. */
    protected static function booted(): void
    {
        $forget = static fn (self $user) => DB::afterCommit(static fn () => app(IamRpcService::class)->forgetUser($user->id));

        self::saved($forget);
        self::deleted($forget);
    }

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
