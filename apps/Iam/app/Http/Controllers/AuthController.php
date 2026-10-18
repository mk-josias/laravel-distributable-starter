<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Controllers;

use Apps\Iam\Actions\IssueToken;
use Apps\Iam\Actions\RefreshToken;
use Apps\Iam\Actions\RegisterUser;
use Apps\Iam\Actions\RevokeTokens;
use Apps\Iam\Http\Requests\LoginRequest;
use Apps\Iam\Http\Requests\LogoutRequest;
use Apps\Iam\Http\Requests\RefreshRequest;
use Apps\Iam\Http\Requests\RegisterRequest;
use Apps\Iam\Models\User;
use Foundation\Common\Http\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    /** No token here: the client logs in next, as on every other device. */
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $user = $register->execute(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return $this->created(['id' => $user->getKey(), 'name' => $user->name]);
    }

    /** The same 401 for an unknown email and a wrong password: the answer never tells which emails exist. */
    public function login(LoginRequest $request, IssueToken $issue): JsonResponse
    {
        $request->ensureIsNotRateLimited();
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $request->recordFailure();

            throw new AuthenticationException;
        }

        $request->clearFailures();

        return $this->created($issue->execute($user, $request->device()));
    }

    /** The same 401 for an unknown, expired or already spent refresh token. */
    public function refresh(RefreshRequest $request, RefreshToken $refresh): JsonResponse
    {
        return $this->created($refresh->execute($request->string('refresh_token')->toString()) ?? throw new AuthenticationException);
    }

    public function me(): JsonResponse
    {
        return $this->success(['id' => $this->principalId()]);
    }

    public function logout(LogoutRequest $request, RevokeTokens $revoke): JsonResponse
    {
        $revoke->execute($this->principalId(), $request->device());

        return $this->noContent();
    }

    public function logoutAll(RevokeTokens $revoke): JsonResponse
    {
        $revoke->execute($this->principalId(), null);

        return $this->noContent();
    }
}
