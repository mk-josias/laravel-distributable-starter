<?php

declare(strict_types=1);

namespace Apps\Analytics\Providers;

use Apps\Analytics\Handlers\RecordMail;
use Apps\Analytics\Handlers\RecordSignup;
use Distributable\Providers\ServiceProvider;
use Foundation\Iam\Events\IamEvent;
use Foundation\Iam\Shadows\UserShadow;
use Foundation\Notifications\Events\NotificationsEvent;
use Illuminate\Console\Scheduling\Schedule;

final class AnalyticsServiceProvider extends ServiceProvider
{
    protected array $streamHandlers = [
        IamEvent::UserRegistered->value => [RecordSignup::class],
        NotificationsEvent::MailSent->value => [RecordMail::class],
    ];

    protected array $shadows = [UserShadow::class];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('analytics:compute')->hourly();
        $schedule->command('analytics:prune')->monthly();
    }
}
