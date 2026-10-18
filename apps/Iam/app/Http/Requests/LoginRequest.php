<?php

declare(strict_types=1);

namespace Apps\Iam\Http\Requests;

use Foundation\Common\Http\ApiRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Five failed logins for one email from one address, then 429 for a minute; a success clears the count. */
final class LoginRequest extends ApiRequest
{
    use IdentifiesDevice;

    private const int MAX_FAILURES = 5;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_FAILURES)) {
            throw new ThrottleRequestsException;
        }
    }

    public function recordFailure(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearFailures(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
