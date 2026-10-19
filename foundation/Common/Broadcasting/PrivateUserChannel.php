<?php

declare(strict_types=1);

namespace Foundation\Common\Broadcasting;

use Foundation\Common\Contracts\Auth\Principal;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;

/** The one channel a person listens on, whatever module pushes to it: only that person may join it. */
final class PrivateUserChannel extends PrivateChannel
{
    public const string NAME = 'user';

    public function __construct(int $userId)
    {
        parent::__construct(self::NAME.'.'.$userId);
    }

    public static function register(): void
    {
        Broadcast::channel(self::NAME.'.{userId}', static fn (Principal $user, int $userId): bool => (int) $user->id() === $userId);
    }
}
