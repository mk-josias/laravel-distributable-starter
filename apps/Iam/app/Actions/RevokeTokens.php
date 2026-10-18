<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Models\User;
use Foundation\Common\Contracts\Action;

/** Logout of one device, or of every device when $device is null. A `signature` JWT already issued stays valid until its expiry. */
final readonly class RevokeTokens implements Action
{
    public function execute(int $userId, ?string $device): void
    {
        User::query()->find($userId)?->tokens()
            ->when($device !== null, fn ($tokens) => $tokens->whereIn('name', [$device, "refresh:{$device}"]))
            ->delete();
    }
}
