<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // An API request is never redirected to a login page. Godot's
        // HTTPRequest sends no Accept header of its own, so Laravel took an
        // unauthenticated call for a browser visit, tried to redirect it to
        // a route this app does not have, and returned a 500 — which the
        // game would have shown a child as "the server is broken".
        //
        // Returning null here makes the auth middleware throw instead, and
        // the handler below turns that into a 401 the game can read.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Not signed in is 401 with a reason the game can show as it
        // stands, never a redirect to a page that does not exist.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'unauthenticated',
                    'message' => 'برای این کار باید وارد حساب خود شوی.',
                ], 401);
            }

            return null;
        });
    })->create();
