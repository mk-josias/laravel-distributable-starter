<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Dto\LoginDto;
use Apps\Iam\Repositories\UserRepository;
use Foundation\Common\Contracts\Actions\Action;
use Illuminate\Support\Facades\Hash;

final readonly class LoginUser implements Action
{
    public function __construct(private UserRepository $users, private IssueToken $issue) {}

    /** @return array{token: string, expires_at: int, refresh_token?: string}|null null for an unknown email and a wrong password alike */
    public function execute(LoginDto $dto): ?array
    {
        $user = $this->users->whereFirst(['email' => $dto->email]);

        return $user === null || ! Hash::check($dto->password, $user->password) ? null : $this->issue->execute($user, $dto->device);
    }
}
