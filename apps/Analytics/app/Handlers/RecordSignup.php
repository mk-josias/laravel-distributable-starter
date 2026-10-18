<?php

declare(strict_types=1);

namespace Apps\Analytics\Handlers;

use Apps\Analytics\Models\Signup;
use Distributable\Contracts\Stream\Handler;
use Foundation\Iam\Events\UserRegisteredPayload;

final class RecordSignup implements Handler
{
    public function handle(string $name, array $payload): void
    {
        $user = UserRegisteredPayload::from($payload);

        Signup::query()->firstOrCreate(['user_id' => $user->id]);
    }
}
