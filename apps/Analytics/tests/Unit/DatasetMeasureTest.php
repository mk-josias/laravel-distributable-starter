<?php

namespace Apps\Analytics\Tests\Unit;

use Apps\Analytics\Enums\DatasetMeasure;
use PHPUnit\Framework\TestCase;

class DatasetMeasureTest extends TestCase
{
    public function test_a_flow_adds_up_its_buckets_and_a_state_keeps_the_last(): void
    {
        $this->assertSame(6, DatasetMeasure::SignupsCount->fold(collect([1, 2, 3])));
        $this->assertSame(3, DatasetMeasure::UsersTotal->fold(collect([1, 2, 3])));
    }
}
