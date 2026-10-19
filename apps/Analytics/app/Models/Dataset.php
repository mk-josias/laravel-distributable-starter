<?php

declare(strict_types=1);

namespace Apps\Analytics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The figures of one hour, computed from the journals by analytics:compute: every reading folds these rows.
 *
 * @property Carbon $bucket
 * @property int $signups_count
 * @property int $mails_sent_count
 * @property int $users_total
 */
final class Dataset extends Model
{
    protected $table = 'analytics_datasets';

    protected $primaryKey = 'bucket';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['bucket', 'signups_count', 'mails_sent_count', 'users_total'];

    /** @param Builder<Dataset> $query */
    public function scopeStartingAt(Builder $query, mixed $from): void
    {
        $query->where('bucket', '>=', Carbon::parse($from));
    }

    /** @param Builder<Dataset> $query */
    public function scopeEndingAt(Builder $query, mixed $to): void
    {
        $query->where('bucket', '<', Carbon::parse($to));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bucket' => 'datetime',
            'signups_count' => 'integer',
            'mails_sent_count' => 'integer',
            'users_total' => 'integer',
        ];
    }
}
