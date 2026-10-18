<?php

declare(strict_types=1);

namespace Apps\Analytics\Console;

use Apps\Analytics\Models\Dataset;
use Apps\Analytics\Models\Mail;
use Apps\Analytics\Models\Signup;
use Apps\Analytics\Services\MonthlyPartitions;
use DateTimeInterface;
use Distributable\Context\ModuleContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A dataset row is derived: each day is rebuilt from the journals, so a day computed twice ends the
 * same. Every measure here is recomputable from them, the total of users included: it counts the
 * signups up to the end of the hour.
 */
final class ComputeDatasets extends Command
{
    protected $signature = 'analytics:compute
        {--date= : A day to (re)compute}
        {--from= : First day of a range, to catch up on history}
        {--to= : Last day of the range, defaults to today}';

    protected $description = 'Rebuild the hourly datasets of yesterday and today, of a day, or of a range, from the journals';

    public function handle(MonthlyPartitions $partitions, ModuleContext $context): int
    {
        $buckets = 0;
        $owner = DB::connection($context->current()?->ownerConnection());

        foreach ($this->days() as $day) {
            $partitions->ensure($day, $owner);
            $buckets += $this->compute($day);
        }

        $this->info("{$buckets} hourly buckets computed.");

        return self::SUCCESS;
    }

    /**
     * Bare, yesterday and today: the hourly schedule then never misses the last hour of a day.
     *
     * @return list<Carbon>
     */
    private function days(): array
    {
        $date = $this->option('date');
        $first = Carbon::parse((string) ($this->option('from') ?? $date ?? 'yesterday'))->startOfDay();
        $last = Carbon::parse((string) ($this->option('to') ?? $date ?? 'today'))->startOfDay();
        $days = [];

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->copy()->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    private function compute(Carbon $day): int
    {
        $next = $day->copy()->addDay();
        $signups = $this->perHour(Signup::query()->where('created_at', '>=', $day)->where('created_at', '<', $next)->pluck('created_at'));
        $mails = $this->perHour(Mail::query()->where('created_at', '>=', $day)->where('created_at', '<', $next)->pluck('created_at'));
        $total = Signup::query()->where('created_at', '<', $day)->count();
        $hours = $signups->keys()->merge($mails->keys())->unique()->sort()->values();

        DB::transaction(function () use ($day, $next, $hours, $signups, $mails, &$total): void {
            Dataset::query()->where('bucket', '>=', $day)->where('bucket', '<', $next)->delete();

            foreach ($hours as $hour) {
                $total += $signups->get($hour, 0);

                Dataset::query()->create([
                    'bucket' => $hour,
                    'signups_count' => $signups->get($hour, 0),
                    'mails_sent_count' => $mails->get($hour, 0),
                    'users_total' => $total,
                ]);
            }
        });

        return $hours->count();
    }

    /**
     * @param  Collection<int, mixed>  $moments
     * @return Collection<string, int>
     */
    private function perHour(Collection $moments): Collection
    {
        return $moments->countBy(fn (mixed $at): string => Carbon::parse($at instanceof DateTimeInterface ? $at : (string) $at)->startOfHour()->toDateTimeString());
    }
}
