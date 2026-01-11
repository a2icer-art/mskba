<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
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

        $exceptions->render(function (Throwable $e, Request $request) use ($renderErrorPage) {
            return $renderErrorPage($request, 500, 'Внутренняя ошибка сервера.');
        });
    })->create();
