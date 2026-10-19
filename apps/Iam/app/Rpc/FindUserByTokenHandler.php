<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Foundation\Iam\Rpc\FindUserByToken;

final class FindUserByTokenHandler
{
    public function __construct(private UserRepository $users) {}

    /** @return array{id: int, name: string}|null */
    public function handle(FindUserByToken $message): ?array
    {
        $user = $this->users->byAccessToken($message->token);

        return $user === null ? null : ['id' => (int) $user->getKey(), 'name' => (string) $user->name];
    }
}
