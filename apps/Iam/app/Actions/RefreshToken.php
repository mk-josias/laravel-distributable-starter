<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Dto\RefreshDto;
use Apps\Iam\Models\AccessToken;
use Apps\Iam\Models\User;
use Foundation\Common\Contracts\Actions\Action;
use Illuminate\Support\Str;

/** Spends a refresh token: its device gets a new session, the token itself is deleted. */
final readonly class RefreshToken implements Action
{
    public function __construct(private IssueToken $issue) {}

    /** @return array{token: string, expires_at: int, refresh_token?: string}|null null for an unknown, expired or spent token */
    public function execute(RefreshDto $dto): ?array
    {
        $token = AccessToken::findToken($dto->refreshToken);

        if (! $token instanceof AccessToken || ! $token->isUsableFor('refresh') || ! $token->tokenable instanceof User) {
            return null;
        }

        return $this->issue->execute($token->tokenable, Str::after($token->name, 'refresh:'));
    }
}
