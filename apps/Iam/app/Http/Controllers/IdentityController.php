<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Controllers;

use Apps\Iam\Services\IamService;
use Foundation\Iam\Auth\JwtTokens;
use Foundation\Iam\Auth\TrustedIdentity;
use Foundation\Iam\Services\IamRpcService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** What the gateway asks: a valid token, a JWT or an opaque one, gets the X-Identity the modules trust. */
final class IdentityController
{
    /** findUser() goes through the shared RPC cache, so a JWT's call also fills the key the gateway reads next time. */
    public function __invoke(Request $request, JwtTokens $tokens, IamRpcService $iam, IamService $local): Response
    {
        $token = (string) $request->bearerToken();
        $opaque = substr_count($token, '.') !== 2 ? $local->findUserByToken($token) : null;
        $claims = $opaque !== null ? ['sub' => $opaque['id'], 'name' => $opaque['name']] : ($tokens->validate($token)->claims ?? []);
        $id = $claims['sub'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id)) || $iam->findUser((int) $id) === null) {
            return response()->noContent(401);
        }

        $signed = TrustedIdentity::sign($claims, time() + 60, (string) config('auth.gateway.secret'));

        return response()->noContent()->header((string) config('auth.gateway.header'), $signed);
    }
}
