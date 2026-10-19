<?php

declare(strict_types=1);

namespace Apps\Notifications\Actions;

use Apps\Notifications\Dto\MarkNotificationReadDto;
use Apps\Notifications\Models\Notification;
use Apps\Notifications\Repositories\NotificationRepository;
use Foundation\Common\Contracts\Actions\Action;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class MarkNotificationRead implements Action
{
    public function __construct(private NotificationRepository $notifications) {}

    /** @throws ModelNotFoundException when the notification is someone else's */
    public function execute(MarkNotificationReadDto $dto): Notification
    {
        return $this->notifications->markRead($this->notifications->findInbox($dto->recipientId, $dto->id));
    }
}
