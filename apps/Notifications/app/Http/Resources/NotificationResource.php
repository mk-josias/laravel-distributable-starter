<?php

declare(strict_types=1);

namespace Apps\Notifications\Http\Resources;

use Apps\Notifications\Models\Notification;
use Foundation\Common\Http\Resources\Resource;
use Illuminate\Http\Request;

/** @mixin Notification */
final class NotificationResource extends Resource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->type->title($this->resource),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
