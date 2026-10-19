<?php

declare(strict_types=1);

namespace Apps\Notifications\Repositories;

use Apps\Notifications\Dto\ListNotificationsDto;
use Apps\Notifications\Models\Notification;
use Closure;
use Foundation\Common\Repositories\EloquentRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends EloquentRepository<Notification> */
final class NotificationRepository extends EloquentRepository
{
    protected string $model = Notification::class;

    protected string $defaultSort = '-id';

    public function __construct()
    {
        parent::__construct(
            filters: [AllowedFilter::scope('unread')],
            sorts: ['id', 'created_at'],
        );
    }

    /**
     * The recipient's notifications, filtered and sorted as the request asks.
     *
     * @return Collection<int, Notification>|LengthAwarePaginator<int, Notification>
     */
    public function inbox(ListNotificationsDto $dto): Collection|LengthAwarePaginator
    {
        return $this->all($dto->query, $this->addressedTo($dto->recipientId));
    }

    /** @throws ModelNotFoundException when the notification is someone else's */
    public function findInbox(int $recipientId, int $id): Notification
    {
        return $this->model::query()->tap($this->addressedTo($recipientId))->findOrFail($id);
    }

    /** The single write path of a notification's reading. */
    public function markRead(Notification $notification): Notification
    {
        $notification->read_at ??= now();
        $notification->save();

        return $notification;
    }

    /** @return Closure(Builder<Notification>): void */
    private function addressedTo(int $recipientId): Closure
    {
        return fn (Builder $query) => $query->addressedTo($recipientId)->with('recipient');
    }
}
