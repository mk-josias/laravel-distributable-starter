<?php

declare(strict_types=1);

namespace Apps\Iam\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A session of one device: an `access` token under `issuer`, a `refresh` one under `signature`; only its hash is stored.
 *
 * @property string $name
 * @property Carbon|null $expires_at
 */
final class AccessToken extends PersonalAccessToken
{
    protected $table = 'iam_access_tokens';

    /** Spent, expired or of another ability: none of them opens a session. */
    public function isUsableFor(string $ability): bool
    {
        return $this->can($ability) && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
