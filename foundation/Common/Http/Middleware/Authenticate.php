<?php

declare(strict_types=1);

namespace Foundation\Common\Http\Middleware;

use Closure;
use Foundation\Common\Auth\Identity;
use Foundation\Common\Contracts\Auth\PrincipalResolver;
use Foundation\Common\Contracts\Auth\TokenValidator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** The token proves an identity, the resolver turns it into the user, or the request 401s. */
class Authenticate
{
    public function __construct(private readonly TokenValidator $tokens, private readonly PrincipalResolver $principals) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->resolve($request);
        $principal = $identity === null ? null : $this->principals->resolve($identity);

        if ($principal === null) {
            throw new AuthenticationException;
        }

        // Set the guard user (not just the request resolver) so authorize()/Gate sees it.
        Auth::setUser($principal);

        return $next($request);
    }

    /** Only the X-Identity the gateway signed: the client's own token never reaches a module's check. */
    protected function resolve(Request $request): ?Identity
    {
        $token = $request->header((string) config('auth.gateway.header'));

        return is_string($token) && $token !== '' ? $this->tokens->validate($token) : null;
    }
}
