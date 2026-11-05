<?php

use App\Services\MediaSources\Exceptions\MediaNotFoundException;
use App\Services\MediaSources\Exceptions\MediaSourceUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // One uniform error shape per exception type, regardless of which
        // external source raised it. See
        // docs/adr/0001-media-source-integration-architecture.md.
        $exceptions->render(fn (MediaNotFoundException $e) => response()->json(['message' => $e->getMessage()], 404));
        $exceptions->render(fn (MediaSourceUnavailableException $e) => response()->json(['message' => $e->getMessage()], 503));
    })->create();
