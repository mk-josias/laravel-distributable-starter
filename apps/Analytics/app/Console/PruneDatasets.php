<?php

declare(strict_types=1);

namespace Apps\Analytics\Console;

use Apps\Analytics\Database\MonthlyPartitions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** The datasets keep a number of months; the journals they are computed from are not touched. */
final class PruneDatasets extends Command
{
    protected $signature = 'analytics:prune
        {--keep=12 : Months kept, the current one included}';

    protected $description = 'Drop the datasets of the months older than the ones kept';

    public function handle(MonthlyPartitions $partitions): int
    {
        $keep = (int) $this->option('keep');

        if ($keep < 1) {
            $this->error('--keep must be at least 1.');

            return self::FAILURE;
        }

        $dropped = $partitions->prune(Carbon::now()->startOfMonth()->subMonthsNoOverflow($keep - 1), DB::connection('owner'));

        $this->info($dropped === [] ? 'Nothing to prune.' : 'Pruned: '.implode(', ', $dropped).'.');

        return self::SUCCESS;
    }
}
