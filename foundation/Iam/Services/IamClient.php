<?php

declare(strict_types=1);

namespace Foundation\Iam\Services;

use Distributable\Services\Rpc\RpcClient;
use Foundation\Iam\Rpc\FindUser;
use Foundation\Iam\Rpc\FindUserByToken;
use Foundation\Iam\Rpc\Grants;

final class IamClient extends RpcClient
{
    /** @return array{id: int, name: string}|null */
    public function findUser(int $id): ?array
    {
        return $this->readThrough(
            self::userKey($id),
            self::DEFAULT_TTL,
            fn (): mixed => $this->send(new FindUser($id)),
            fn (array $raw): array => ['id' => (int) $raw['id'], 'name' => (string) $raw['name']],
        );
    }

    /** Not cached: a token is checked on every request, and a revoked one must stop working at once. */
    /** @return array{id: int, name: string}|null */
    public function findUserByToken(string $token): ?array
    {
        $raw = $this->send(new FindUserByToken($token));

        return is_array($raw) ? ['id' => (int) $raw['id'], 'name' => (string) $raw['name']] : null;
    }

    /** @return list<string> */
    public function grants(int $userId): array
    {
        return $this->readThrough(
            $this->grantsKey($userId),
            self::DEFAULT_TTL,
            fn (): mixed => $this->send(new Grants($userId)),
            fn (array $raw): array => array_values(array_map(strval(...), $raw)),
        ) ?? [];
    }

    /** iam calls it when a user changes, so the other processes read the new one. */
    public function forgetUser(int $id): void
    {
        $this->forget(self::userKey($id));
    }

    /** iam calls it once a user's roles or permissions change. */
    public function forgetGrants(int $userId): void
    {
        $this->forget($this->grantsKey($userId));
    }

    /** iam calls it once a role's permissions change: every user's grants are read again, on any cache store. */
    public function flushGrants(): void
    {
        $this->cache()->forever(self::GRANTS_GENERATION, $this->generation() + 1);
    }

    private const string GRANTS_GENERATION = 'iam:grants:generation';

    private function grantsKey(int $userId): string
    {
        return "iam:grants:{$this->generation()}:{$userId}";
    }

    private function generation(): int
    {
        return (int) $this->cache()->get(self::GRANTS_GENERATION, 0);
    }

    private static function userKey(int $id): string
    {
        return "iam:user:{$id}";
    }
}
