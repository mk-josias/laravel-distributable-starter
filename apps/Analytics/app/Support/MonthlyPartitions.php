<?php

declare(strict_types=1);

namespace Apps\Analytics\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * The monthly partitions of analytics_datasets, by the driver of the module's connection. PostgreSQL:
 * one table per month, a row cannot land in a month without one. MySQL and MariaDB: RANGE COLUMNS on
 * `bucket`, the lowest partition holding every earlier row. An expired month leaves by DETACH or DROP
 * PARTITION, without a DELETE walking its rows; elsewhere the table is plain and retention a DELETE.
 * Partitions are DDL, so they run on the module's owner connection.
 */
final class MonthlyPartitions
{
    public const string TABLE = 'analytics_datasets';

    /** MySQL and MariaDB create the table with its first partition: it cannot exist without one. */
    public static function firstPartition(Carbon $at): string
    {
        $month = $at->copy()->startOfMonth();

        return sprintf("PARTITION %s VALUES LESS THAN ('%s')", self::name($month), $month->copy()->addMonthNoOverflow()->toDateString());
    }

    /** Opens the month of $at and the one after it, so the next hour never finds its month missing. */
    public function ensure(Carbon $at, Connection $owner): void
    {
        $months = [$at->copy()->startOfMonth(), $at->copy()->addMonthNoOverflow()->startOfMonth()];

        match ($owner->getDriverName()) {
            'pgsql' => $this->ensureOnPostgres($months, $owner),
            'mysql', 'mariadb' => $this->ensureOnMysql($months, $owner),
            default => null,
        };
    }

    /**
     * Drops every month that ends on or before $before; on a plain table, deletes its rows.
     *
     * @return list<string> the months dropped, as Y-m
     */
    public function prune(Carbon $before, Connection $owner): array
    {
        $before = $before->copy()->startOfMonth();

        $dropped = match ($owner->getDriverName()) {
            'pgsql' => $this->pruneOnPostgres($before, $owner),
            'mysql', 'mariadb' => $this->pruneOnMysql($before, $owner),
            default => $this->pruneRows($before, $owner),
        };

        sort($dropped);

        return $dropped;
    }

    /** @param list<Carbon> $months */
    private function ensureOnPostgres(array $months, Connection $owner): void
    {
        foreach ($months as $month) {
            $owner->statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
                self::name($month),
                self::TABLE,
                $month->toDateString(),
                $month->copy()->addMonthNoOverflow()->toDateString(),
            ));
        }
    }

    /**
     * A partition is added above the highest one only: a month below it already lands in an existing one.
     *
     * @param  list<Carbon>  $months
     */
    private function ensureOnMysql(array $months, Connection $owner): void
    {
        foreach ($months as $month) {
            $upper = $month->copy()->addMonthNoOverflow();
            $partitions = $this->mysqlPartitions($owner);
            $highest = $partitions === [] ? null : $partitions[array_key_last($partitions)]['upper'];

            if ($highest === null || $upper->greaterThan($highest)) {
                $owner->statement(sprintf('ALTER TABLE %s ADD PARTITION (PARTITION %s VALUES LESS THAN (\'%s\'))', self::TABLE, self::name($month), $upper->toDateString()));
            }
        }
    }

    /** @return list<string> */
    private function pruneOnPostgres(Carbon $before, Connection $owner): array
    {
        $dropped = [];

        foreach ($owner->select('SELECT inhrelid::regclass::text AS name FROM pg_inherits WHERE inhparent = ?::regclass', [self::TABLE]) as $partition) {
            $name = (string) $partition->name;
            $month = Carbon::createFromFormat('Y_m', substr($name, -7))->startOfMonth();

            if ($month->lessThan($before)) {
                $owner->statement('ALTER TABLE '.self::TABLE." DETACH PARTITION {$name}");
                $owner->statement("DROP TABLE {$name}");
                $dropped[] = $month->format('Y-m');
            }
        }

        return $dropped;
    }

    /**
     * The highest partition is never dropped: MySQL refuses a table without one.
     *
     * @return list<string>
     */
    private function pruneOnMysql(Carbon $before, Connection $owner): array
    {
        $partitions = $this->mysqlPartitions($owner);
        array_pop($partitions);
        $dropped = [];

        foreach ($partitions as $partition) {
            if ($partition['upper']->lessThanOrEqualTo($before)) {
                $owner->statement(sprintf('ALTER TABLE %s DROP PARTITION %s', self::TABLE, $partition['name']));
                $dropped[] = $partition['upper']->copy()->subMonthNoOverflow()->format('Y-m');
            }
        }

        return $dropped;
    }

    /** @return list<string> */
    private function pruneRows(Carbon $before, Connection $owner): array
    {
        $months = $owner->table(self::TABLE)->where('bucket', '<', $before)->pluck('bucket')
            ->map(fn (mixed $bucket): string => Carbon::parse((string) $bucket)->format('Y-m'))->unique()->values()->all();
        $owner->table(self::TABLE)->where('bucket', '<', $before)->delete();

        return $months;
    }

    /** @return list<array{name: string, upper: Carbon}> lowest first */
    private function mysqlPartitions(Connection $owner): array
    {
        $rows = $owner->select(
            'SELECT PARTITION_NAME AS name, PARTITION_DESCRIPTION AS upper FROM information_schema.PARTITIONS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL ORDER BY PARTITION_ORDINAL_POSITION',
            [self::TABLE],
        );

        return array_values(array_map(
            static fn (object $row): array => ['name' => (string) $row->name, 'upper' => Carbon::parse(trim((string) $row->upper, "'"))],
            $rows,
        ));
    }

    private static function name(Carbon $month): string
    {
        return self::TABLE.'_'.$month->format('Y_m');
    }
}
