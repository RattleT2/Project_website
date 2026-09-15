<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->status !== 'aktif') {
            return response()->json(['message' => 'Akun Anda non-aktif. Hubungi admin.'], 403);
        }

        return $next($request);
    }
}
