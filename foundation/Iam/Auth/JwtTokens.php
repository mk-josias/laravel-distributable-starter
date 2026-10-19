<?php

declare(strict_types=1);

namespace Foundation\Iam\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Foundation\Common\Auth\Identity;
use Foundation\Common\Contracts\Auth\TokenValidator;
use InvalidArgumentException;
use Throwable;

/** The token is a JWT: the module checks it with its issuer's public key, without calling the issuer. */
final readonly class JwtTokens implements TokenValidator
{
    private Key $key;

    public function __construct(string $publicKey)
    {
        if ($publicKey === '') {
            throw new InvalidArgumentException('The jwt strategy needs AUTH_JWT_PUBLIC_KEY.');
        }

        $this->key = new Key($publicKey, 'RS256');
    }

    public function validate(string $token): ?Identity
    {
        try {
            /** @var array<string, mixed> $claims */
            $claims = json_decode((string) json_encode(JWT::decode($token, $this->key)), true);
        } catch (Throwable) {
            return null;
        }

        $subject = $claims['sub'] ?? null;

        if (! is_string($subject) && ! is_int($subject) || $subject === '') {
            return null;
        }

        return new Identity(ctype_digit((string) $subject) ? (int) $subject : $subject, $claims);
    }
}
