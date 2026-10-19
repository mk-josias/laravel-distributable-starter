<?php

declare(strict_types=1);

use Apps\Iam\Http\Controllers\Api\AuthController;
use Foundation\Common\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // login limits itself per email and address (LoginRequest); these two per address, each on its own counter.
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1,register');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:6,1,refresh');

    Route::middleware(Authenticate::class)->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});
