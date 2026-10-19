<?php

declare(strict_types=1);

namespace Apps\Analytics\Http\Requests;

use Apps\Analytics\Dto\ReadMetricsDto;
use Apps\Analytics\Enums\DatasetGroup;
use Apps\Analytics\Enums\DatasetMeasure;
use Foundation\Common\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

final class ReadMetricsRequest extends ApiRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'group' => ['sometimes', Rule::enum(DatasetGroup::class)],
            'measures' => ['sometimes', 'array'],
            'measures.*' => [Rule::enum(DatasetMeasure::class)],
            'filter.from' => ['sometimes', 'date'],
            'filter.to' => ['sometimes', 'date', 'after:filter.from'],
        ];
    }

    public function toDto(): ReadMetricsDto
    {
        $measures = $this->validated('measures');

        return new ReadMetricsDto(
            DatasetGroup::from($this->validated('group', DatasetGroup::Day->value)),
            is_array($measures) ? array_values(array_map(DatasetMeasure::from(...), $measures)) : DatasetMeasure::cases(),
        );
    }
}
