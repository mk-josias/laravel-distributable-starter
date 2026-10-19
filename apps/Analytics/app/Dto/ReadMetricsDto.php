<?php

declare(strict_types=1);

namespace Apps\Analytics\Dto;

use Apps\Analytics\Enums\DatasetGroup;
use Apps\Analytics\Enums\DatasetMeasure;
use Foundation\Common\Dto\Dto;

final class ReadMetricsDto extends Dto
{
    /** @param list<DatasetMeasure> $measures */
    public function __construct(public readonly DatasetGroup $group, public readonly array $measures) {}
}
