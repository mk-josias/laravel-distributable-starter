<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Dto\RevokeTokensDto;
use Apps\Iam\Repositories\UserRepository;
use Foundation\Common\Contracts\Actions\Action;

/** Logout of one device, or of every device when $device is null. A `signature` JWT already issued stays valid until its expiry. */
final readonly class RevokeTokens implements Action
{
    public function __construct(private UserRepository $users) {}

    public function execute(RevokeTokensDto $dto): void
    {
        $this->users->retrieve($dto->userId)?->tokens()
            ->when($dto->device !== null, fn ($tokens) => $tokens->whereIn('name', [$dto->device, "refresh:{$dto->device}"]))
            ->delete();
    }
}
