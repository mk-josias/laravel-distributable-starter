<?php

declare(strict_types=1);

namespace Foundation\Notifications\Events;

use Distributable\Contracts\Stream\Versioned;
use Foundation\Common\Data\Dto;

/** What notifications.mail.sent carries: which notification left, to whom, about what. */
final class MailSentPayload extends Dto implements Versioned
{
    public function __construct(
        public int $notification_id,
        public int $user_id,
        public string $type,
    ) {}

    public static function version(): int
    {
        return 1;
    }

    public static function upcast(int $from, array $payload): array
    {
        return $payload;
    }
}
