<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Foundation\Common\Http\ApiRequest;

final class RefreshRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['refresh_token' => ['required', 'string']];
    }
}
