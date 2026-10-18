<?php

namespace Tests\Integration;

use Tests\TestCase;

class EventsAcrossModulesTest extends TestCase
{
    public function test_analytics_records_the_signup_and_keeps_a_copy_of_the_user_once_it_consumes_the_events(): void
    {
        $id = $this->register()->json('data.id');
        $headers = $this->throughGateway($this->login());

        $this->artisan('stream:consume --module=analytics')->assertSuccessful();

        $this->getJson('/analytics/api/v1/signups', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $id)
            ->assertJsonPath('data.0.user.name', 'Ada');
    }
}
