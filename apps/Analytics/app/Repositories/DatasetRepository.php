<?php

declare(strict_types=1);

namespace Apps\Analytics\Repositories;

use Apps\Analytics\Dto\ReadMetricsDto;
use Apps\Analytics\Enums\DatasetGroup;
use Apps\Analytics\Enums\DatasetMeasure;
use Apps\Analytics\Models\Dataset;
use Closure;
use Foundation\Common\Repositories\EloquentRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The one read of analytics_datasets: the hourly rows computed by analytics:compute, folded onto
 * intervals or onto one figure. How a measure folds is not a choice (MeasureNature): a flow adds up,
 * a state keeps the last value of the period.
 *
 * @extends EloquentRepository<Dataset>
 */
final class DatasetRepository extends EloquentRepository
{
    protected string $model = Dataset::class;

    protected string $defaultSort = 'bucket';

    public function __construct()
    {
        parent::__construct(
            filters: [AllowedFilter::scope('from', 'startingAt'), AllowedFilter::scope('to', 'endingAt')],
            sorts: ['bucket'],
        );
    }

    /**
     * The intervals: the measures asked for, folded onto every hour, day, week or month of the period.
     * With group `none`, the whole period is one bucket (bucket null).
     *
     * @param  Closure(Builder<Dataset>): void|null  $constrain  the caller's perimeter
     * @return list<array<string, int|string|null>>
     */
    public function read(ReadMetricsDto $dto, ?Closure $constrain = null): array
    {
        return $this->builder($constrain)->get()
            ->groupBy(fn (Dataset $row): string => (string) $dto->group->bucket($row->bucket))
            ->map(fn (Collection $rows, string $bucket): array => ['bucket' => $bucket === '' ? null : $bucket, ...$this->fold($rows, $dto->measures)])
            ->values()
            ->all();
    }

    /** One figure: a measure folded over the period, 0 when no row falls in it. */
    public function measure(DatasetMeasure $measure, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $period = fn (Builder $query) => $query
            ->when($from, fn (Builder $query, Carbon $from) => $query->startingAt($from))
            ->when($to, fn (Builder $query, Carbon $to) => $query->endingAt($to));

        return (int) ($this->read(new ReadMetricsDto(DatasetGroup::None, [$measure]), $period)[0][$measure->value] ?? 0);
    }

    /**
     * @param  Collection<int, Dataset>  $rows
     * @param  list<DatasetMeasure>  $measures
     * @return array<string, int>
     */
    private function fold(Collection $rows, array $measures): array
    {
        $figures = [];

        foreach ($measures as $measure) {
            $figures[$measure->value] = $measure->fold($rows->map(fn (Dataset $row): int => (int) $row->getAttribute($measure->value)));
        }

        return $figures;
    }
}
