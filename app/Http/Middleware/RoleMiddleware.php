<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->status !== 'aktif') {
            auth('api')->logout();
            return response()->json(['message' => 'Akun Anda non-aktif. Hubungi admin.'], 403);
        }

        if ($user->role !== $role) {
            return response()->json(['message' => 'Anda tidak memiliki akses.'], 403);
        }

        return $next($request);
    }
}
