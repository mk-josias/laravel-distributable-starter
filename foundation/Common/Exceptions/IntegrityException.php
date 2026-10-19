<?php

declare(strict_types=1);

namespace Foundation\Common\Exceptions;

use Foundation\Common\Contracts\Http\RendersApiEnvelope;
use RuntimeException;
use Throwable;

/**
 * An integrity violation (phantom reference, broken invariant): a SYSTEM error (http 500), not a
 * business one. It belongs to no module, so it carries a raw system code rather than a
 * module-localized message — hence `errorCode` rather than `business_code`.
 */
class IntegrityException extends RuntimeException implements RendersApiEnvelope
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $errorCode,
        public readonly array $context = [],
        public readonly int $http_code = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, 0, $previous);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function message(): string
    {
        return $this->getMessage();
    }

    public function httpCode(): int
    {
        return $this->http_code;
    }
}
