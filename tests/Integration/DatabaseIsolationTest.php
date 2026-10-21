<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_registering_a_user_writes_to_the_iam_database_only(): void
    {
        $this->register()
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonStructure(['success', 'data' => ['id', 'name']])
            ->assertJsonMissingPath('data.token');

        $this->assertSame(1, $this->inModule('iam', fn () => DB::table('iam_users')->count()));
        $this->assertSame(0, $this->inModule('analytics', fn () => DB::table('iam_users')->count()));
    }
}
