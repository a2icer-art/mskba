<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'confirmed.role' => \App\Http\Middleware\EnsureConfirmedRoleLevel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderErrorPage = static function (Request $request, int $status, string $message) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return null;
            }

            return Inertia::render('Error', [
                'status' => $status,
                'message' => $message,
                'appName' => config('app.name'),
            ])->toResponse($request)->setStatusCode($status);
        };

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($renderErrorPage) {
            $status = $e->getStatusCode();
            if ($status === 419) {
                $userId = $request->user()?->id;
                $headers = $request->headers;
                $cookies = $request->cookies;

                logger()->warning('CSRF token mismatch.', [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'user_id' => $userId,
                    'referer' => $headers->get('referer'),
                    'origin' => $headers->get('origin'),
                    'user_agent' => $headers->get('user-agent'),
                    'session_id' => $request->session()?->getId(),
                    'has_x_csrf_token' => $headers->has('x-csrf-token'),
                    'has_x_xsrf_token' => $headers->has('x-xsrf-token'),
                    'has_x_requested_with' => $headers->has('x-requested-with'),
                    'has_xsrf_cookie' => $cookies->has('XSRF-TOKEN'),
                    'has_session_cookie' => $cookies->has(config('session.cookie')),
                    'xsrf_cookie_length' => $cookies->has('XSRF-TOKEN')
                        ? strlen((string) $cookies->get('XSRF-TOKEN'))
                        : 0,
                ]);

                $target = $headers->get('referer') ?: url()->previous() ?: '/';
                $message = 'Сессия обновилась, страница перезагружена.';
                session()->flash('info', $message);

                if ($request->header('X-Inertia')) {
                    return Inertia::location($target);
                }

                return redirect()->to($target);
            }
            if (!in_array($status, [403, 404, 500], true)) {
                return null;
            }

            $message = match ($status) {
                403 => 'Доступ запрещен.',
                404 => 'Страница не найдена.',
                default => 'Внутренняя ошибка сервера.',
            };

            return $renderErrorPage($request, $status, $message);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($renderErrorPage) {
            return $renderErrorPage($request, 404, 'Страница не найдена.');
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return redirect()->guest(route('login'));
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $userId = $request->user()?->id;
            $headers = $request->headers;
            $cookies = $request->cookies;

            logger()->warning('CSRF token mismatch.', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $userId,
                'referer' => $headers->get('referer'),
                'origin' => $headers->get('origin'),
                'user_agent' => $headers->get('user-agent'),
                'session_id' => $request->session()?->getId(),
                'has_x_csrf_token' => $headers->has('x-csrf-token'),
                'has_x_xsrf_token' => $headers->has('x-xsrf-token'),
                'has_x_requested_with' => $headers->has('x-requested-with'),
                'has_xsrf_cookie' => $cookies->has('XSRF-TOKEN'),
                'has_session_cookie' => $cookies->has(config('session.cookie')),
                'xsrf_cookie_length' => $cookies->has('XSRF-TOKEN')
                    ? strlen((string) $cookies->get('XSRF-TOKEN'))
                    : 0,
            ]);

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($renderErrorPage) {
            if ($e instanceof ValidationException) {
                return null;
            }

            logger()->error($e->getMessage(), ['exception' => $e]);

            return $renderErrorPage($request, 500, 'Внутренняя ошибка сервера.');
        });
    })->create();
