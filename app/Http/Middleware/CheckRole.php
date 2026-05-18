<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Пропускает запрос, если $request->user()->role входит в переданные ID
     * (пример в routes: middleware('role:'.User::ROLE_ADMIN)).
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  ID ролей (User::ROLE_ADMIN = 1, …)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        $roleIds = array_map('intval', $roles);

        if (!$user || !in_array((int) $user->role, $roleIds, true)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        return $next($request);
    }
}
