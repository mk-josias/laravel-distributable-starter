<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Rpc\Grants;

/** @implements Handler<Grants> */
final class GrantsHandler implements Handler
{
    public function __construct(private UserRepository $users) {}

    /** @return list<string> the permissions the user holds, through its roles or directly */
    public function handle(Grants $message): array
    {
        $user = $this->users->retrieve($message->userId);

        return $user === null ? [] : array_values($user->getAllPermissions()->pluck('name')->map(strval(...))->all());
    }
}
