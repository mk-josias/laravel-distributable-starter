<?php

declare(strict_types=1);

namespace Foundation\Common\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;

/** Free-text scope over the columns searchable() lists, dotted paths included. */
trait Searchable
{
    /** @return list<string> */
    abstract protected function searchable(): array;

    /**
     * @param  Builder<BaseModel>  $q
     * @return Builder<BaseModel>
     */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        // Case-insensitive on every driver: ilike is PostgreSQL-only.
        $operator = $q->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $q->where(function (Builder $q) use ($term, $operator) {
            foreach ($this->searchable() as $col) {
                if (str_contains($col, '.')) {
                    $parts = explode('.', $col);
                    $q->orWhereHas($parts[0], fn ($r) => $r->where($parts[1], $operator, "%{$term}%"));
                } else {
                    $q->orWhere($col, $operator, "%{$term}%");
                }
            }
        });
    }
}
