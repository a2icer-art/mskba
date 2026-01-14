<?php

namespace App\Http\Middleware;

use App\Domain\Users\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConfirmedRoleLevel
{
    public function handle(Request $request, Closure $next, string $minLevel = '0'): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ($user->status?->value !== UserStatus::Confirmed->value) {
            abort(403);
        }

        $roleLevel = (int) $user->roles()->max('level');
        if ($roleLevel <= (int) $minLevel) {
            abort(403);
        }

        return $next($request);
    }
}
