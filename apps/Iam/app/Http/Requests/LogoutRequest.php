<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Foundation\Common\Http\ApiRequest;

final class LogoutRequest extends ApiRequest
{
    use IdentifiesDevice;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
