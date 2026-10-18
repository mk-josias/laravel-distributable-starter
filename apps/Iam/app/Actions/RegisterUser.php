<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Events\UserRegistered;
use Apps\Iam\Models\User;
use Distributable\Contracts\Stream\Bus;
use Foundation\Common\Contracts\Action;
use Foundation\Iam\Events\UserRegisteredPayload;
use Illuminate\Support\Facades\DB;

final readonly class RegisterUser implements Action
{
    public function __construct(private Bus $bus) {}

    /** No token here: the client logs in with POST login, as on every other device. */
    public function execute(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);

            $this->bus->emit(new UserRegistered(new UserRegisteredPayload(id: $user->id)));

            return $user;
        });
    }
}
