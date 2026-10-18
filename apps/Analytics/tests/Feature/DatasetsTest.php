<?php

namespace Apps\Analytics\Tests\Feature;

use Apps\Analytics\Models\Dataset;
use Foundation\Analytics\Enums\AnalyticsPermission;
use Foundation\Iam\Events\IamEvent;
use Foundation\Notifications\Events\NotificationsEvent;
use Tests\ModuleTestCase;

class DatasetsTest extends ModuleTestCase
{
    protected string $module = 'analytics';

    public function test_a_reading_folds_the_hourly_rows_a_flow_adding_up_and_a_state_keeping_the_last(): void
    {
        $headers = $this->signedUpAt(1, 'ada', '2026-03-02 09:15');
        $this->signedUpAt(2, 'grace', '2026-03-02 09:40');
        $this->signedUpAt(3, 'linus', '2026-03-03 14:05');
        $this->mailedAt(10, 1, '2026-03-02 09:16');

        $this->artisan('analytics:compute --from=2026-03-02 --to=2026-03-03')->expectsOutput('2 hourly buckets computed.')->assertSuccessful();

        $this->getJson('/analytics/api/v1/datasets?group=hour', $headers)->assertOk()->assertExactJson(['success' => true, 'data' => [
            ['bucket' => '2026-03-02 09:00', 'signups_count' => 2, 'mails_sent_count' => 1, 'users_total' => 2],
            ['bucket' => '2026-03-03 14:00', 'signups_count' => 1, 'mails_sent_count' => 0, 'users_total' => 3],
        ]]);

        $this->getJson('/analytics/api/v1/datasets?group=none&measures[]=signups_count', $headers)
            ->assertOk()->assertExactJson(['success' => true, 'data' => [['bucket' => null, 'signups_count' => 3]]]);

        $this->getJson('/analytics/api/v1/datasets?group=month&from=2026-03-03', $headers)
            ->assertOk()->assertExactJson(['success' => true, 'data' => [['bucket' => '2026-03', 'signups_count' => 1, 'mails_sent_count' => 0, 'users_total' => 3]]]);
    }

    public function test_a_day_computed_again_ends_the_same_and_only_that_day_is_rebuilt(): void
    {
        $this->signedUpAt(1, 'ada', '2026-03-02 09:15');
        $this->signedUpAt(2, 'grace', '2026-03-03 14:05');
        $this->artisan('analytics:compute --from=2026-03-02 --to=2026-03-03')->assertSuccessful();

        $this->signedUpAt(3, 'linus', '2026-03-03 14:30');
        $this->artisan('analytics:compute --date=2026-03-03')->expectsOutput('1 hourly buckets computed.')->assertSuccessful();
        $this->artisan('analytics:compute --date=2026-03-03')->assertSuccessful();

        $this->inModule('analytics', fn () => $this->assertSame(
            [['2026-03-02 09:00:00', 1, 1], ['2026-03-03 14:00:00', 2, 3]],
            Dataset::query()->orderBy('bucket')->get()->map(fn (Dataset $row): array => [$row->bucket->toDateTimeString(), $row->signups_count, $row->users_total])->all(),
        ));
    }

    public function test_the_months_past_the_ones_kept_are_pruned(): void
    {
        $this->travelTo('2026-05-10 12:00');
        $this->signedUpAt(1, 'ada', '2026-02-02 09:15');
        $this->signedUpAt(2, 'grace', '2026-04-03 14:05');
        $this->artisan('analytics:compute --from=2026-02-01 --to=2026-04-30')->assertSuccessful();

        $this->artisan('analytics:prune --keep=2')->expectsOutput('Pruned: 2026-02.')->assertSuccessful();

        $this->inModule('analytics', fn () => $this->assertSame(['2026-04-03 14:00:00'], Dataset::query()->pluck('bucket')->map->toDateTimeString()->all()));
    }

    public function test_an_unknown_group_or_measure_is_refused(): void
    {
        $this->getJson('/analytics/api/v1/datasets?group=year&measures[]=revenue', $this->signedUpAt(1, 'ada', '2026-03-02 09:15'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group', 'measures.0']);
    }

    public function test_reading_the_datasets_needs_the_permission_iam_grants(): void
    {
        $this->getJson('/analytics/api/v1/datasets', $this->user(1, 'ada'))->assertForbidden();
        $this->getJson('/analytics/api/v1/datasets', $this->user(2, 'grace', [AnalyticsPermission::ReadDatasets->value]))->assertOk();
    }

    /** @return array{X-Identity: string} */
    private function signedUpAt(int $id, string $name, string $at): array
    {
        $now = now();
        $this->travelTo($at);
        $this->receive('analytics', IamEvent::UserRegistered->value, ['id' => $id]);
        $this->travelTo($now);

        return $this->user($id, $name, [AnalyticsPermission::ReadDatasets->value]);
    }

    private function mailedAt(int $notification, int $user, string $at): void
    {
        $now = now();
        $this->travelTo($at);
        $this->receive('analytics', NotificationsEvent::MailSent->value, ['notification_id' => $notification, 'user_id' => $user, 'type' => 'user.welcome']);
        $this->travelTo($now);
    }
}
