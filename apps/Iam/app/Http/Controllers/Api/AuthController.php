<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Controllers\Api;

use Apps\Iam\Actions\LoginUser;
use Apps\Iam\Actions\RefreshToken;
use Apps\Iam\Actions\RegisterUser;
use Apps\Iam\Actions\RevokeTokens;
use Apps\Iam\Http\Requests\LoginRequest;
use Apps\Iam\Http\Requests\LogoutRequest;
use Apps\Iam\Http\Requests\RefreshRequest;
use Apps\Iam\Http\Requests\RegisterRequest;
use Foundation\Common\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

final class AuthController extends Controller
{
    /** No token here: the client logs in next, as on every other device. */
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $user = $register->execute($request->toDto());

        return $this->created(['id' => $user->getKey(), 'name' => $user->name]);
    }

    /** The same 401 for an unknown email and a wrong password: the answer never tells which emails exist. */
    public function login(LoginRequest $request, LoginUser $login): JsonResponse
    {
        $request->ensureIsNotRateLimited();
        $session = $login->execute($request->toDto());

        if ($session === null) {
            $request->recordFailure();

            throw new AuthenticationException;
        }

        $request->clearFailures();

        return $this->created($session);
    }

    /** The same 401 for an unknown, expired or already spent refresh token. */
    public function refresh(RefreshRequest $request, RefreshToken $refresh): JsonResponse
    {
        return $this->created($refresh->execute($request->toDto()) ?? throw new AuthenticationException);
    }

    public function me(): JsonResponse
    {
        return $this->success(['id' => $this->principalId()]);
    }

    public function logout(LogoutRequest $request, RevokeTokens $revoke): JsonResponse
    {
        $revoke->execute($request->toDto());

        return $this->noContent();
    }

    public function logoutAll(LogoutRequest $request, RevokeTokens $revoke): JsonResponse
    {
        $revoke->execute($request->toDto(everyDevice: true));

        return $this->noContent();
    }
}
