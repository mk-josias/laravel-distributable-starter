<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Apps\Iam\Dto\RegisterUserDto;
use Apps\Iam\Models\User;
use Foundation\Common\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

final class RegisterRequest extends ApiRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function toDto(): RegisterUserDto
    {
        return RegisterUserDto::from($this->validated());
    }
}
