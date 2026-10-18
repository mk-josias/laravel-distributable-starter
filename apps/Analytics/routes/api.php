<?php

declare(strict_types=1);

use Apps\Analytics\Http\Controllers\DatasetController;
use Apps\Analytics\Http\Controllers\SignupController;
use Foundation\Common\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(Authenticate::class)->group(function (): void {
    Route::get('signups', [SignupController::class, 'index']);
    Route::get('users/{id}', [SignupController::class, 'user']);
    Route::get('datasets', [DatasetController::class, 'index']);
});
