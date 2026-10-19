<?php

declare(strict_types=1);

use Apps\Analytics\Http\Controllers\Api\MetricController;
use Foundation\Common\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(Authenticate::class)->group(function (): void {
    Route::get('metrics', [MetricController::class, 'index']);
});
