<?php

declare(strict_types=1);

namespace Apps\Notifications\Handlers;

use Apps\Notifications\Enums\NotificationType;
use Apps\Notifications\Models\Notification;
use Distributable\Contracts\Stream\Handler;
use Foundation\Iam\Events\UserRegisteredPayload;

final class SendWelcome implements Handler
{
    public function handle(string $name, array $payload): void
    {
        $user = UserRegisteredPayload::from($payload);

        Notification::query()->firstOrCreate(
            ['recipient_user_id' => $user->id, 'type' => NotificationType::Welcome],
            ['payload' => []],
        );
    }
}
