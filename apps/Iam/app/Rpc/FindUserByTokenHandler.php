<?php

declare(strict_types=1);

namespace Apps\Iam\Rpc;

use Apps\Iam\Repositories\UserRepository;
use Apps\Iam\Services\GatewayCache;
use Distributable\Contracts\Rpc\Handler;
use Foundation\Iam\Data\UserData;
use Foundation\Iam\Rpc\FindUserByToken;

/** @implements Handler<FindUserByToken> */
final class FindUserByTokenHandler implements Handler
{
    public function __construct(private UserRepository $users, private GatewayCache $gateway) {}

    /** The user a bearer token opens, written for the gateway to read next time; null when it opens nothing. */
    public function handle(FindUserByToken $message): ?UserData
    {
        $user = $this->users->byToken($message->token);

        if ($user === null) {
            return null;
        }

        $this->gateway->rememberUser(['id' => (int) $user->getKey()]);

        return new UserData((int) $user->getKey(), (string) $user->name);
    }
}
