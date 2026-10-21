<?php

declare(strict_types=1);

namespace Apps\Notifications\Events;

use Distributable\Attributes\Emits;
use Distributable\Events\Event;
use Foundation\Notifications\Events\MailSentPayload;
use Foundation\Notifications\Events\NotificationsEvent;

#[Emits(NotificationsEvent::MailSent)]
final class MailSent extends Event
{
    public function __construct(private readonly MailSentPayload $mail) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->mail->toArray();
    }
}
