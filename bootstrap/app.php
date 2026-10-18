<?php

use Foundation\Common\Contracts\RendersApiEnvelope;
use Foundation\Common\Http\ApiErrorCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || array_intersect(['api', (string) config('rpc-http.middleware_group', 'rpc')], (array) $request->route()?->middleware()) !== [],
        );

        // The envelope is rendered here, once, for every exception that describes one — never
        // by the exception itself. This is the application's shape, not the package's.
        $exceptions->renderable(fn (RendersApiEnvelope $e) => response()->json([
            'success' => false,
            'code' => $e->code(),
            'message' => $e->message(),
        ], $e->httpCode()));

        $exceptions->renderable(fn (AuthenticationException $e, Request $request) => $request->expectsJson()
            ? response()->json([
                'success' => false,
                'code' => ApiErrorCode::Unauthenticated->value,
                'message' => __('auth.unauthenticated'),
            ], 401)
            : null);
    })->create();
