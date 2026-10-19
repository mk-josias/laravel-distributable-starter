<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Data\UserData;
use Foundation\Iam\Rpc\FindUserByToken;

/** @implements Handler<FindUserByToken> */
final class FindUserByTokenHandler implements Handler
{
    public function __construct(private UserRepository $users) {}

    public function handle(FindUserByToken $message): ?UserData
    {
        $user = $this->users->byAccessToken($message->token);

        return $user === null ? null : new UserData((int) $user->getKey(), (string) $user->name);
    }
}
