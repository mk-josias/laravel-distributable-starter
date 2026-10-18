<?php

declare(strict_types=1);

namespace Foundation;

use Distributable\Providers\FoundationServiceProvider as BaseServiceProvider;
use Foundation\Common\Auth\PermissionSource;
use Foundation\Common\Auth\PrincipalResolver;
use Foundation\Common\Auth\TokenValidator;
use Foundation\Common\Broadcasting\PrivateUserChannel;
use Foundation\Iam\Auth\JwtTokens;
use Foundation\Iam\Auth\TrustedIdentity;
use Foundation\Iam\Contracts\IamService;
use Foundation\Iam\Events\IamEvent;
use Foundation\Iam\Events\UserRegisteredPayload;
use Foundation\Iam\Services\IamRpcService;
use Foundation\Notifications\Events\MailSentPayload;
use Foundation\Notifications\Events\NotificationsEvent;
use Illuminate\Contracts\Foundation\Application;

final class FoundationServiceProvider extends BaseServiceProvider
{
    protected array $rpc = [
        IamService::class => IamRpcService::class,
    ];

    protected array $payloads = [
        IamEvent::UserRegistered->value => UserRegisteredPayload::class,
        NotificationsEvent::MailSent->value => MailSentPayload::class,
    ];

    /** A module reads only the gateway's X-Identity; the user and its permissions come from classes named in config/auth.php. */
    public function register(): void
    {
        $this->app->when(JwtTokens::class)->needs('$publicKey')->give(fn (): string => (string) config('auth.tokens.jwt.public_key'));
        $this->app->when(TrustedIdentity::class)->needs('$secret')->give(fn (): string => (string) config('auth.gateway.secret'));

        $this->app->bind(TokenValidator::class, TrustedIdentity::class);

        $this->app->bind(PrincipalResolver::class, fn (Application $app): PrincipalResolver => $app->make((string) config('auth.principal_resolver')));
        $this->app->bind(PermissionSource::class, fn (Application $app): PermissionSource => $app->make((string) config('auth.permission_source')));
    }

    public function boot(): void
    {
        parent::boot();

        PrivateUserChannel::register();
    }
}
