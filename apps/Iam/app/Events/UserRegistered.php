<?php

declare(strict_types=1);

namespace Apps\Iam\Events;

use Distributable\Events\Event;
use Foundation\Iam\Events\IamEvent;
use Foundation\Iam\Events\UserRegisteredPayload;

final class UserRegistered extends Event
{
    public function __construct(private readonly UserRegisteredPayload $user) {}

    public function name(): string
    {
        return IamEvent::UserRegistered->value;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->user->toArray();
    }

    public function version(): int
    {
        return UserRegisteredPayload::version();
    }
}
