<?php

declare(strict_types=1);

namespace Foundation\Iam\Rpc;

use Distributable\Attributes\Rpc;
use Distributable\Contracts\Rpc\Message;

#[Rpc(module: 'iam')]
final readonly class Grants implements Message
{
    public function __construct(public int $userId) {}

    public static function rules(): array
    {
        return ['userId' => ['required', 'integer']];
    }
}
