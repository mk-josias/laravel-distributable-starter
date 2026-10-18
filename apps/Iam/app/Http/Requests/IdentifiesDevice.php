<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

/** The device a session belongs to: X-Device-Id, or else a hash of the user agent. */
trait IdentifiesDevice
{
    public function device(): string
    {
        return (string) ($this->header('X-Device-Id') ?: hash('sha256', (string) $this->userAgent()));
    }
}
