<?php

declare(strict_types=1);

namespace Foundation\Common\Dto;

use Spatie\LaravelData\Concerns\TransformableData;
use Spatie\LaravelData\Contracts\TransformableData as TransformableDataContract;
use Spatie\LaravelData\Dto as BaseDto;

abstract class Dto extends BaseDto implements TransformableDataContract
{
    use TransformableData;
}
