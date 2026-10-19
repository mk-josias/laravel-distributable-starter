<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Apps\Iam\Dto\RefreshDto;
use Foundation\Common\Http\Requests\ApiRequest;

final class RefreshRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['refresh_token' => ['required', 'string']];
    }

    public function toDto(): RefreshDto
    {
        return RefreshDto::from($this->validated());
    }
}
