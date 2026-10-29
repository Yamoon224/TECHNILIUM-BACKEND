<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a TLS-terminating proxy the app sees plain http: trust X-Forwarded-* so generated
        // URLs (Swagger assets, docs JSON) keep the https scheme instead of being blocked as mixed content.
        $middleware->trustProxies(at: '*');

        // Same reasoning as shouldRenderJsonWhen below: there is no "login"
        // web route in this API-only backend, so the framework's default
        // guest redirect (route('login')) must never be resolved.
        $middleware->redirectGuestsTo(fn () => null);

        // Audit trail of back-office actions (cahier §6.15): every API request
        // passes through it; it decides itself which ones to record.
        $middleware->api(append: [\App\Modules\Audit\Http\Middleware\RecordAudit::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is an API-only backend: there is no "login" web route to
        // redirect unauthenticated requests to. Laravel's default error
        // rendering only returns JSON when the client's Accept header
        // says so (`expectsJson()`) — any other client hitting a
        // protected /api/* route crashes with a 500 (RouteNotFoundException
        // for "login") instead of a clean 401/404/422. Every /api/*
        // response must be JSON regardless of what the client sent.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
