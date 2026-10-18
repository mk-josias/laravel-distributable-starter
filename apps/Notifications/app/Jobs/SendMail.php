<?php

declare(strict_types=1);

namespace Apps\Notifications\Jobs;

use Apps\Notifications\Events\MailSent;
use Apps\Notifications\Mail\NotificationMail;
use Apps\Notifications\Models\Notification;
use Distributable\Contracts\Stream\Bus;
use Foundation\Notifications\Events\MailSentPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendMail implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Notification $notification) {}

    public function handle(Bus $bus): void
    {
        $recipient = $this->notification->recipient;

        if ($recipient === null) {
            return;
        }

        Mail::to($recipient->email)->send(new NotificationMail($this->notification));

        $bus->emit(new MailSent(new MailSentPayload(
            notification_id: $this->notification->id,
            user_id: $this->notification->recipient_user_id,
            type: $this->notification->type->value,
        )));
    }
}
