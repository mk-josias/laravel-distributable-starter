<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Data\UserData;
use Foundation\Iam\Rpc\FindUser;

/** @implements Handler<FindUser> */
final class FindUserHandler implements Handler
{
    public function __construct(private UserRepository $users) {}

    public function handle(FindUser $message): ?UserData
    {
        $user = $this->users->retrieve($message->id);

        return $user === null ? null : new UserData((int) $user->getKey(), (string) $user->name);
    }
}
