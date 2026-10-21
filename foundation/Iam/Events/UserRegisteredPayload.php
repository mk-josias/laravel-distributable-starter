<?php

declare(strict_types=1);

namespace Foundation\Iam\Events;

use Distributable\Attributes\Payload;
use Distributable\Contracts\Stream\Versioned;
use Foundation\Common\Dto\Dto;

/** What iam.user.registered carries: what a consumer needs without calling iam back. */
#[Payload(IamEvent::UserRegistered)]
final class UserRegisteredPayload extends Dto implements Versioned
{
    public function __construct(
        public int $id,
    ) {}

    /** Renaming, removing or redefining a field raises the version of #[Payload] and adds its arm here: `1 => [...$payload, 'locale' => 'en']`. */
    public static function upcast(int $from, array $payload): array
    {
        return $payload;
    }
}
