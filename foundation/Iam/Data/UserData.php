<?php

declare(strict_types=1);

namespace Foundation\Iam\Data;

use Spatie\LaravelData\Data;

/** What iam answers about a user: the identifier and the name every module may read. */
final class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
