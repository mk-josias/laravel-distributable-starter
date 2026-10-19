<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Models\User;
use Firebase\JWT\JWT;
use Foundation\Common\Contracts\Actions\Action;

/**
 * auth.tokens.validation: `signature`, a JWT the gateway checks with iam's public key, with a refresh token when
 * auth.tokens.refresh_ttl is set; `issuer`, an opaque token only iam reads. One session per device: a new one replaces it.
 */
final readonly class IssueToken implements Action
{
    /** @return array{token: string, expires_at: int, refresh_token?: string} */
    public function execute(User $user, string $device): array
    {
        $user->tokens()->whereIn('name', [$device, "refresh:{$device}"])->delete();

        if (config('auth.tokens.validation') === 'issuer') {
            $expiresAt = now()->addSeconds((int) config('auth.tokens.ttl'));

            return ['token' => $user->createToken($device, ['access'], $expiresAt)->plainTextToken, 'expires_at' => $expiresAt->getTimestamp()];
        }

        $expiresAt = time() + (int) config('auth.tokens.ttl');
        $session = [
            'token' => JWT::encode(['sub' => (string) $user->getKey(), 'iat' => time(), 'exp' => $expiresAt], (string) config('auth.tokens.jwt.private_key'), 'RS256'),
            'expires_at' => $expiresAt,
        ];
        $refreshTtl = (int) config('auth.tokens.refresh_ttl');

        return $refreshTtl > 0
            ? [...$session, 'refresh_token' => $user->createToken("refresh:{$device}", ['refresh'], now()->addSeconds($refreshTtl))->plainTextToken]
            : $session;
    }
}
