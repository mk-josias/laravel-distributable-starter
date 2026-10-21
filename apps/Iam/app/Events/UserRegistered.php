<?php

declare(strict_types=1);

namespace Apps\Iam\Events;

use Distributable\Attributes\Emits;
use Distributable\Events\Event;
use Foundation\Iam\Events\IamEvent;
use Foundation\Iam\Events\UserRegisteredPayload;

#[Emits(IamEvent::UserRegistered)]
final class UserRegistered extends Event
{
    public function __construct(private readonly UserRegisteredPayload $user) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->user->toArray();
    }
}
