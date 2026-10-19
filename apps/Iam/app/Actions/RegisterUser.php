<?php

declare(strict_types=1);

namespace Apps\Iam\Actions;

use Apps\Iam\Dto\RegisterUserDto;
use Apps\Iam\Events\UserRegistered;
use Apps\Iam\Models\User;
use Apps\Iam\Repositories\UserRepository;
use Distributable\Contracts\Stream\Bus;
use Foundation\Common\Contracts\Actions\Action;
use Foundation\Iam\Events\UserRegisteredPayload;
use Illuminate\Support\Facades\DB;

final readonly class RegisterUser implements Action
{
    public function __construct(private Bus $bus, private UserRepository $users) {}

    /** No token here: the client logs in with POST login, as on every other device. */
    public function execute(RegisterUserDto $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $user = $this->users->create($dto->toArray());

            $this->bus->emit(new UserRegistered(new UserRegisteredPayload(id: $user->id)));

            return $user;
        });
    }
}
