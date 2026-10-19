<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Controllers\Api;

use Apps\Iam\Actions\IssueIdentity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** What the gateway asks: a valid token, a JWT or an opaque one, gets the X-Identity the modules trust. */
final class IdentityController
{
    public function __invoke(Request $request, IssueIdentity $issue): Response
    {
        $identity = $issue->execute((string) $request->bearerToken());

        return $identity === null
            ? response()->noContent(401)
            : response()->noContent()->header((string) config('auth.gateway.header'), $identity);
    }
}
