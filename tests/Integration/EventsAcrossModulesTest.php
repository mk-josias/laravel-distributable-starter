<?php

namespace Tests\Integration;

use Apps\Analytics\Models\Signup;
use Foundation\Iam\Shadows\UserShadow;
use Tests\TestCase;

class EventsAcrossModulesTest extends TestCase
{
    public function test_analytics_records_the_signup_and_keeps_a_copy_of_the_user_once_it_consumes_the_events(): void
    {
        $id = $this->register()->json('data.id');

        $this->artisan('stream:consume --module=analytics')->assertSuccessful();

        $this->inModule('analytics', function () use ($id): void {
            $this->assertSame([$id], Signup::query()->pluck('user_id')->all());
            $this->assertSame('Ada', UserShadow::query()->findOrFail($id)->name);
        });
    }
}
