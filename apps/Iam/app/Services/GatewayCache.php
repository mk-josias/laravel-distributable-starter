<?php

declare(strict_types=1);

namespace Apps\Iam\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/** What the gateway reads in gateway-cache, which only iam writes: the users it knows, as JSON under gateway:user:{id}. */
final class GatewayCache
{
    private const string KEY = 'gateway:user:';

    /** Seconds a user stays: a write missed between a commit and here is gone by then. */
    private const int TTL = 3600;

    /** @param array{id: int} $user */
    public function rememberUser(array $user): void
    {
        $this->connection()?->setex(self::KEY.$user['id'], self::TTL, json_encode(['id' => $user['id']], JSON_THROW_ON_ERROR));
    }

    public function forgetUser(int $id): void
    {
        $this->connection()?->del(self::KEY.$id);
    }

    private function connection(): ?Connection
    {
        $name = config('auth.gateway.users_connection');

        return is_string($name) && $name !== '' ? Redis::connection($name) : null;
    }
}
