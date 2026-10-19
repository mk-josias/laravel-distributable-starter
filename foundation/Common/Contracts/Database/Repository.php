<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Not the DDD abstraction: two concrete roles only — the declarative HTTP query surface
 * (allowed filters/sorts/includes, caller perimeter, pagination) and the single write path
 * (where cache invalidation lives). A model exposing neither does not need one.
 *
 * @template TModel of Model
 */
interface Repository
{
    /**
     * @param  array<string, mixed>  $queries
     * @param  Closure(Builder<TModel>): void|null  $constrain
     * @return Collection<int, TModel>|LengthAwarePaginator<int, TModel>
     */
    public function all(array $queries = [], ?Closure $constrain = null): Collection|LengthAwarePaginator;

    /** @return TModel|null */
    public function retrieve(int $id): ?Model;

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function retrieveOrFail(int $id): Model;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    public function collect(array $ids): Collection;

    /**
     * @param  array<string, mixed>  $conditions
     * @return Collection<int, TModel>
     */
    public function where(array $conditions): Collection;

    /**
     * @param  array<string, mixed>  $conditions
     * @return TModel|null
     */
    public function whereFirst(array $conditions): ?Model;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(int $id, array $data): Model;

    public function delete(int $id): void;

    public function restore(int $id): void;
}
