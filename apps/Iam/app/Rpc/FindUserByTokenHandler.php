<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Rpc\FindUserByToken;

/** @implements Handler<FindUserByToken> */
final class FindUserByTokenHandler implements Handler
{
    public function __construct(private UserRepository $users) {}

    /** @return array{id: int, name: string}|null */
    public function handle(FindUserByToken $message): ?array
    {
        $user = $this->users->byAccessToken($message->token);

        return $user === null ? null : ['id' => (int) $user->getKey(), 'name' => (string) $user->name];
    }
}
