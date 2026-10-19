<?php

declare(strict_types=1);

namespace Apps\Iam\Dto;

use Foundation\Common\Dto\Dto;

final class RegisterUserDto extends Dto
{
    public function __construct(public readonly string $name, public readonly string $email, public readonly string $password) {}
}
