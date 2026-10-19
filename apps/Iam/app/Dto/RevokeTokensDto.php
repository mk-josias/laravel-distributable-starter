<?php

declare(strict_types=1);

namespace Apps\Iam\Dto;

use Foundation\Common\Dto\Dto;

final class RevokeTokensDto extends Dto
{
    public function __construct(public readonly int $userId, public readonly ?string $device) {}
}
