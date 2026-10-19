<?php

declare(strict_types=1);

namespace Apps\Notifications\Http\Requests;

use Apps\Notifications\Dto\MarkNotificationReadDto;
use Foundation\Common\Http\Requests\ApiRequest;

final class MarkNotificationReadRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): MarkNotificationReadDto
    {
        return new MarkNotificationReadDto((int) $this->route('id'), $this->principalIdOrFail());
    }
}
