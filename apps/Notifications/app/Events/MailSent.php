<?php

declare(strict_types=1);

namespace Apps\Notifications\Events;

use Distributable\Events\Event;
use Foundation\Notifications\Events\MailSentPayload;
use Foundation\Notifications\Events\NotificationsEvent;

final class MailSent extends Event
{
    public function __construct(private readonly MailSentPayload $mail) {}

    public function name(): string
    {
        return NotificationsEvent::MailSent->value;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->mail->toArray();
    }

    public function version(): int
    {
        return MailSentPayload::version();
    }
}
