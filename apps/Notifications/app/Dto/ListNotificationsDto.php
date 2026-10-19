<?php

declare(strict_types=1);

namespace Apps\Notifications\Dto;

use Foundation\Common\Dto\Dto;

final class ListNotificationsDto extends Dto
{
    /** @param array<string, mixed> $query the request's filters, sort and page */
    public function __construct(public readonly int $recipientId, public readonly array $query) {}
}
