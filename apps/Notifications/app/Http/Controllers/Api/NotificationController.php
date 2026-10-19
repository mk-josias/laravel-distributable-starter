<?php

declare(strict_types=1);

namespace Apps\Notifications\Http\Controllers\Api;

use Apps\Notifications\Actions\MarkNotificationRead;
use Apps\Notifications\Http\Requests\ListNotificationsRequest;
use Apps\Notifications\Http\Requests\MarkNotificationReadRequest;
use Apps\Notifications\Http\Resources\NotificationResource;
use Apps\Notifications\Repositories\NotificationRepository;
use Foundation\Common\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Every query is scoped to the authenticated recipient: someone else's notification is a 404. */
final class NotificationController extends Controller
{
    /** ?filter[unread]=1, ?sort=-created_at, ?paginate=20 */
    public function index(ListNotificationsRequest $request, NotificationRepository $notifications): JsonResponse
    {
        return $this->success(NotificationResource::collection($notifications->inbox($request->toDto())));
    }

    public function read(MarkNotificationReadRequest $request, MarkNotificationRead $mark): JsonResponse
    {
        return $this->success(NotificationResource::make($mark->execute($request->toDto())));
    }
}
