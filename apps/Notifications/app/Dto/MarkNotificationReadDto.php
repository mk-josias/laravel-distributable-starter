<?php

declare(strict_types=1);

namespace Apps\Notifications\Dto;

use Foundation\Common\Dto\Dto;

final class MarkNotificationReadDto extends Dto
{
    public function __construct(public readonly int $id, public readonly int $recipientId) {}
}
