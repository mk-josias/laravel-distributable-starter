<?php

declare(strict_types=1);

namespace Apps\Iam\Repositories;

use Apps\Iam\Models\AccessToken;
use Apps\Iam\Models\User;
use Apps\Iam\Services\GatewayCache;
use Foundation\Common\Repositories\EloquentRepository;
use Foundation\Iam\Auth\JwtTokens;
use Foundation\Iam\Services\IamClient;
use Illuminate\Support\Facades\DB;

/**
 * The single write path of a user: once it is committed, the cached answer of findUser() is forgotten
 * and the gateway's copy is rewritten, or removed with the user.
 *
 * @extends EloquentRepository<User>
 */
final class UserRepository extends EloquentRepository
{
    protected string $model = User::class;

    public function __construct(private readonly IamClient $iam, private readonly GatewayCache $gateway, private readonly JwtTokens $tokens)
    {
        parent::__construct();
    }

    /** The user a bearer token opens, whatever its kind; null when it opens nothing. */
    public function byToken(string $token): ?User
    {
        return substr_count($token, '.') === 2 ? $this->byJwt($token) : $this->byOpaqueToken($token);
    }

    /** The user holding an `access` token not expired: a refresh token never opens a request. */
    private function byOpaqueToken(string $token): ?User
    {
        $access = AccessToken::findToken($token);

        return $access instanceof AccessToken && $access->isUsableFor('access') && $access->tokenable instanceof User ? $access->tokenable : null;
    }

    /** The user a JWT names, once its signature is checked. */
    private function byJwt(string $token): ?User
    {
        $id = $this->tokens->validate($token)?->claims['sub'] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? $this->retrieve((int) $id) : null;
    }

    public function create(array $data): User
    {
        return $this->published(parent::create($data));
    }

    public function update(int $id, array $data): User
    {
        return $this->published(parent::update($id, $data));
    }

    public function delete(int $id): void
    {
        parent::delete($id);

        DB::afterCommit(function () use ($id): void {
            $this->iam->forgetUser($id);
            $this->gateway->forgetUser($id);
        });
    }

    private function published(User $user): User
    {
        DB::afterCommit(function () use ($user): void {
            $this->iam->forgetUser((int) $user->getKey());
            $this->gateway->rememberUser(['id' => (int) $user->getKey()]);
        });

        return $user;
    }
}
