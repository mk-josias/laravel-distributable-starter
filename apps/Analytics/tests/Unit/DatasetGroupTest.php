<?php

namespace Apps\Analytics\Tests\Unit;

use Apps\Analytics\Enums\DatasetGroup;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DatasetGroupTest extends TestCase
{
    public function test_an_hour_falls_in_its_hour_day_week_and_month_and_in_no_bucket_for_none(): void
    {
        $hour = Carbon::parse('2026-10-15 14:00');   // a Thursday

        $this->assertSame('2026-10-15 14:00', DatasetGroup::Hour->bucket($hour));
        $this->assertSame('2026-10-15', DatasetGroup::Day->bucket($hour));
        $this->assertSame('2026-10-12', DatasetGroup::Week->bucket($hour));
        $this->assertSame('2026-10', DatasetGroup::Month->bucket($hour));
        $this->assertNull(DatasetGroup::None->bucket($hour));
    }
}
