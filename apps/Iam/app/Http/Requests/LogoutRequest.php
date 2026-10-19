<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Apps\Iam\Dto\RevokeTokensDto;
use Foundation\Common\Http\Requests\ApiRequest;

final class LogoutRequest extends ApiRequest
{
    use IdentifiesDevice;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    public function toDto(bool $everyDevice = false): RevokeTokensDto
    {
        return new RevokeTokensDto($this->principalIdOrFail(), $everyDevice ? null : $this->device());
    }
}
