<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Repositories\UserRepository;
use Apps\Iam\Services\GatewayCache;
use Foundation\Common\Contracts\Actions\Action;
use Foundation\Iam\Auth\JwtTokens;
use Foundation\Iam\Auth\TrustedIdentity;

final readonly class IssueIdentity implements Action
{
    public function __construct(private UserRepository $users, private JwtTokens $tokens, private GatewayCache $gateway) {}

    /** The X-Identity of the user a bearer token belongs to, signed for the modules; null when the token opens nothing. */
    public function execute(string $token): ?string
    {
        $opaque = $token !== '' && substr_count($token, '.') !== 2 ? $this->users->byAccessToken($token) : null;
        $claims = $opaque !== null ? ['sub' => $opaque->getKey(), 'name' => $opaque->name] : ($this->tokens->validate($token)->claims ?? []);
        $id = $claims['sub'] ?? null;

        $user = $opaque ?? (is_int($id) || (is_string($id) && ctype_digit($id)) ? $this->users->retrieve((int) $id) : null);

        if ($user === null) {
            return null;
        }

        $this->gateway->rememberUser(['id' => (int) $user->getKey()]);

        return TrustedIdentity::sign($claims, time() + 60, (string) config('auth.gateway.secret'));
    }
}
