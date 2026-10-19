<?php

declare(strict_types=1);

namespace Apps\Iam\Dto;

use Foundation\Common\Dto\Dto;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class RefreshDto extends Dto
{
    public function __construct(public readonly string $refreshToken) {}
}
