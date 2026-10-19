<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Models\User;
use Foundation\Common\Contracts\Actions\Action;
use Foundation\Iam\Services\IamClient;
use Illuminate\Support\Facades\DB;

/** Every change to a user's roles goes through here, so its cached grants are forgotten once it is committed. */
final readonly class GrantRole implements Action
{
    public function __construct(private IamClient $iam) {}

    public function grant(User $user, string $role): void
    {
        $user->assignRole($role);
        DB::afterCommit(fn () => $this->iam->forgetGrants($user->id));
    }

    public function revoke(User $user, string $role): void
    {
        $user->removeRole($role);
        DB::afterCommit(fn () => $this->iam->forgetGrants($user->id));
    }
}
