<?php

declare(strict_types=1);

namespace Foundation\Common\Enums;

/** Machine-readable identifiers for the application-level API error envelopes. */
enum ApiErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case Validation = 'VALIDATION';
}
