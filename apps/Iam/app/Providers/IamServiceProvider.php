<?php

declare(strict_types=1);

namespace Apps\Iam\Providers;

use Apps\Iam\Models\AccessToken;
use Apps\Iam\Services\IamService;
use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Contracts\IamService as Contract;
use Laravel\Sanctum\Sanctum;

final class IamServiceProvider extends ServiceProvider
{
    protected array $services = [
        Contract::class => IamService::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Sanctum::usePersonalAccessTokenModel(AccessToken::class);
    }
}
