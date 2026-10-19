<?php

declare(strict_types=1);

namespace Foundation\Iam\Auth;

use Foundation\Common\Auth\Identity;
use Foundation\Common\Contracts\Auth\TokenValidator;

/**
 * X-Identity, written by the gateway once it checked the client's token: `{payload}.{hmac}`, the payload
 * being the token's claims as base64url JSON, `sub` and `exp` among them. A module reads only this; it
 * proves who the gateway vouched for, the PrincipalResolver then finds the user.
 */
final readonly class TrustedIdentity implements TokenValidator
{
    public function __construct(private string $secret) {}

    /** @param array<string, mixed> $claims with `sub`; `exp` is set to $expiresAt */
    public static function sign(array $claims, int $expiresAt, string $secret): string
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode([...$claims, 'exp' => $expiresAt])), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, $secret);
    }

    public function validate(string $token): ?Identity
    {
        [$payload, $signature] = explode('.', $token, 2) + ['', ''];

        if (! hash_equals(hash_hmac('sha256', $payload, $this->secret), $signature)) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        $subject = is_array($claims) ? ($claims['sub'] ?? null) : null;

        if (! is_array($claims) || (int) ($claims['exp'] ?? 0) < time() || ! (is_int($subject) || is_string($subject)) || $subject === '') {
            return null;
        }

        return new Identity(ctype_digit((string) $subject) ? (int) $subject : $subject, $claims);
    }
}
