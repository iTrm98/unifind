<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Render (and similar hosts) terminate HTTPS at a proxy: trust its
        // X-Forwarded-* headers so generated URLs use https.
        $middleware->trustProxies(at: '*');

        // Every API request is treated as a JSON request.
        $middleware->prependToGroup('api', ForceJsonResponse::class);

        // There is no web login page: never redirect API guests.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $isApi($request));

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'You are not signed in or your session has expired.'], 401);
            }
        });

        // Hide model class names and ids from 404 messages.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = $e->getPrevious() instanceof ModelNotFoundException || $e->getMessage() === ''
                    || str_contains($e->getMessage(), 'could not be found')
                    ? 'Not found.'
                    : $e->getMessage();

                return response()->json(['message' => $message], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'This method is not allowed on this endpoint.'], 405);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if ($isApi($request) && $e->getStatusCode() === 429) {
                return response()->json(['message' => 'Too many attempts. Please wait a minute and try again.'], 429, $e->getHeaders());
            }
        });
    })->create();
