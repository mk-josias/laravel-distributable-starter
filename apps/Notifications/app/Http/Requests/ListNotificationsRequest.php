<?php

declare(strict_types=1);

namespace Apps\Notifications\Http\Requests;

use Apps\Notifications\Dto\ListNotificationsDto;
use Foundation\Common\Http\Requests\ApiRequest;

final class ListNotificationsRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['paginate' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    public function toDto(): ListNotificationsDto
    {
        return new ListNotificationsDto($this->principalIdOrFail(), $this->query());
    }
}
