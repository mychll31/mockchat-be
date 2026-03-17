<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->enabled) {
            return response()->json(['error' => 'Your account has been disabled.'], 403);
        }

        return $next($request);
    }
}
