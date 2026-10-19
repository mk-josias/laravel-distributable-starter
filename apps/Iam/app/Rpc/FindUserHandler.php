<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Foundation\Iam\Rpc\FindUser;

final class FindUserHandler
{
    public function __construct(private UserRepository $users) {}

    /** @return array{id: int, name: string}|null */
    public function handle(FindUser $message): ?array
    {
        $user = $this->users->retrieve($message->id);

        return $user === null ? null : ['id' => (int) $user->getKey(), 'name' => (string) $user->name];
    }
}
