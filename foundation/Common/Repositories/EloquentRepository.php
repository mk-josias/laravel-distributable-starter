<?php

declare(strict_types=1);

namespace Foundation\Common\Repositories;

use Closure;
use Foundation\Common\Contracts\Database\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @template TModel of BaseModel
 *
 * @implements Repository<TModel>
 */
abstract class EloquentRepository implements Repository
{
    /** @var class-string<TModel> */
    protected string $model;

    protected string $defaultSort = '-created_at';

    /**
     * @param  list<string|AllowedFilter>  $filters  a plain string is a partial filter; wrap a
     *                                               reference/enum column in AllowedFilter::exact to
     *                                               match it verbatim (partial would break on a bigint).
     * @param  list<string|AllowedSort>  $sorts
     * @param  list<string|AllowedInclude>  $includes
     */
    public function __construct(
        protected array $filters = [],
        protected array $sorts = [],
        protected array $includes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $queries
     * @param  Closure(Builder<TModel>): void|null  $constrain  base constraint applied before the
     *                                                          request filters (e.g. a perimeter WHERE)
     * @return Collection<int, TModel>|LengthAwarePaginator<int, TModel>
     */
    public function all(array $queries = [], ?Closure $constrain = null): Collection|LengthAwarePaginator
    {
        $qb = $this->builder($constrain);

        $paginate = (int) ($queries['paginate'] ?? 0);

        return $paginate > 0 ? $qb->paginate($paginate)->appends($queries) : $qb->get();
    }

    /**
     * The model's query with the filters, sorts and includes this repository allows, read from the
     * current request, and the caller's constraint (a perimeter) applied first.
     *
     * @param  Closure(Builder<TModel>): void|null  $constrain
     * @return QueryBuilder<TModel>
     */
    public function builder(?Closure $constrain = null): QueryBuilder
    {
        $qb = QueryBuilder::for($this->model)
            ->allowedFilters(...$this->filters)
            ->allowedSorts(...$this->sorts)
            ->allowedIncludes(...$this->includes)
            ->defaultSort($this->defaultSort);

        if ($constrain !== null) {
            $constrain($qb->getEloquentBuilder());
        }

        return $qb;
    }

    /** @return TModel|null */
    public function retrieve(int $id): ?BaseModel
    {
        return $this->model::query()->find($id);
    }

    /** @return TModel */
    public function retrieveOrFail(int $id): BaseModel
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    public function collect(array $ids): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->model::query()->whereIn('id', $ids)->get();
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return Collection<int, TModel>
     */
    public function where(array $conditions): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->model::query()->where($conditions)->get();
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return TModel|null
     */
    public function whereFirst(array $conditions): ?BaseModel
    {
        return $this->model::query()->where($conditions)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): BaseModel
    {
        return $this->model::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(int $id, array $data): BaseModel
    {
        $model = $this->retrieveOrFail($id);
        $model->update($data);

        return $model;
    }

    public function delete(int $id): void
    {
        $this->retrieveOrFail($id)->delete();
    }

    public function restore(int $id): void
    {
        $model = $this->model::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->findOrFail($id);

        if (method_exists($model, 'restore')) {
            $model->restore();
        }
    }
}
