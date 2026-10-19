<?php

namespace Apps\Notifications\Tests\Feature;

use Foundation\Iam\Events\IamEvent;
use Tests\ModuleTestCase;

class InboxTest extends ModuleTestCase
{
    protected string $module = 'notifications';

    public function test_a_registered_user_finds_a_welcome_in_the_inbox_and_marks_it_read(): void
    {
        $headers = $this->registered(1, 'ada');

        $this->getJson('/notifications/api/v1/notifications', $headers)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'user.welcome')
            ->assertJsonPath('data.0.title', 'Welcome, ada!')
            ->assertJsonPath('data.0.read_at', null);

        $id = $this->getJson('/notifications/api/v1/notifications?filter[unread]=1', $headers)->json('data.0.id');
        $this->patchJson("/notifications/api/v1/notifications/{$id}/read", [], $headers)->assertOk();

        $this->getJson('/notifications/api/v1/notifications?filter[unread]=1', $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/notifications/api/v1/notifications?filter[unread]=0', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_inbox_pages_and_refuses_a_filter_it_does_not_allow(): void
    {
        $headers = $this->registered(1, 'ada');

        $this->getJson('/notifications/api/v1/notifications?paginate=1', $headers)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/notifications/api/v1/notifications?filter[recipient_user_id]=2', $headers)->assertBadRequest();
    }

    public function test_a_page_size_out_of_range_is_refused(): void
    {
        $this->getJson('/notifications/api/v1/notifications?paginate=0', $this->registered(1, 'ada'))->assertUnprocessable();
    }

    public function test_nobody_reads_or_marks_the_notification_of_someone_else(): void
    {
        $ada = $this->registered(1, 'ada');
        $grace = $this->registered(2, 'grace');
        $adas = $this->getJson('/notifications/api/v1/notifications', $ada)->json('data.0.id');

        $this->getJson('/notifications/api/v1/notifications', $grace)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Welcome, grace!');
        $this->patchJson("/notifications/api/v1/notifications/{$adas}/read", [], $grace)->assertNotFound();
    }

    public function test_a_welcome_is_sent_once_however_many_times_the_event_arrives(): void
    {
        $headers = $this->registered(1, 'ada');
        $this->receive('notifications', IamEvent::UserRegistered->value, ['id' => 1]);

        $this->getJson('/notifications/api/v1/notifications', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    /** @return array{X-Identity: string} */
    private function registered(int $id, string $name): array
    {
        $headers = $this->user($id, $name);
        $this->receive('notifications', IamEvent::UserRegistered->value, ['id' => $id]);

        return $headers;
    }
}
