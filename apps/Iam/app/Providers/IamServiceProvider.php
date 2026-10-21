<?php

declare(strict_types=1);

namespace Apps\Iam\Providers;

use Apps\Iam\Models\AccessToken;
use Apps\Iam\Rpc\FindUserByTokenHandler;
use Apps\Iam\Rpc\FindUserHandler;
use Apps\Iam\Rpc\GrantsHandler;
use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Rpc\FindUser;
use Foundation\Iam\Rpc\FindUserByToken;
use Foundation\Iam\Rpc\Grants;
use Laravel\Sanctum\Sanctum;

final class IamServiceProvider extends ServiceProvider
{
    protected array $rpcHandlers = [
        FindUser::class => FindUserHandler::class,
        FindUserByToken::class => FindUserByTokenHandler::class,
        Grants::class => GrantsHandler::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Sanctum::usePersonalAccessTokenModel(AccessToken::class);
    }
}
