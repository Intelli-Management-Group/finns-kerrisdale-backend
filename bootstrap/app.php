<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleLegacyUrls;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(prepend: [HandleLegacyUrls::class]);
        $middleware->alias(['active' => EnsureUserIsActive::class]);
        $middleware->trustProxies(at: '*');
        // API uses bearer tokens (no cookies), so it is not exposed to CSRF.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Validation failed', 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Unauthenticated. Please log in.', 401);
            }
        });
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('You are not allowed to perform this action.', 403);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Method not allowed.', 405);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Too many requests. Please slow down and try again shortly.', 429)
                    ->withHeaders($e->getHeaders());
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($isApi($request) && ! $e instanceof HttpExceptionInterface) {
                return ApiResponse::error(config('app.debug') ? $e->getMessage() : 'Something went wrong on our side. Please try again.', 500);
            }
            if ($isApi($request) && $e instanceof HttpExceptionInterface) {
                return ApiResponse::error($e->getMessage() ?: 'Request failed.', $e->getStatusCode());
            }
        });
    })->create();
