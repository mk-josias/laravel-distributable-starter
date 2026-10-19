<?php

declare(strict_types=1);

namespace Foundation\Iam\Rpc;

use Distributable\Attributes\Rpc;
use Distributable\Contracts\Rpc\Message;

#[Rpc(module: 'iam')]
final readonly class FindUserByToken implements Message
{
    public function __construct(public string $token) {}

    public static function rules(): array
    {
        return ['token' => ['required', 'string']];
    }
}
