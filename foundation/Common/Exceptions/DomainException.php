<?php

declare(strict_types=1);

namespace Foundation\Common\Exceptions;

use Distributable\Traits\ResolvesModule;
use Foundation\Common\Contracts\Http\RendersApiEnvelope;
use RuntimeException;
use Throwable;

/**
 * Base for module business exceptions: `{Module}Exception extends DomainException`. The message
 * is module-localized (`{module}.errors.{business_code}`), http 400 by default. The exception
 * never renders itself — the handler turns it into the envelope, centrally.
 */
abstract class DomainException extends RuntimeException implements RendersApiEnvelope
{
    use ResolvesModule;

    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $business_code,
        public readonly array $context = [],
        public readonly int $http_code = 400,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            __($this->module->name.'.errors.'.$business_code, $context),
            0,
            $previous,
        );
    }

    public function code(): string
    {
        return $this->business_code;
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
