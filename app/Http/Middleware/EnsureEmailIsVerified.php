<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (!$request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email not verified.'
            ], 403);
        }

        return $next($request);
    }
}
