<?php

declare(strict_types=1);

use Apps\Notifications\Http\Controllers\Api\NotificationController;
use Foundation\Common\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(Authenticate::class)->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'read']);
});

// POST /notifications/api/v1/broadcasting/auth: Echo joins private-user.{id} with the same token as the API.
Broadcast::routes(['prefix' => 'v1', 'middleware' => [Authenticate::class]]);
