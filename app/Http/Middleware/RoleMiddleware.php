<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();

        if (!$user->role) {
            abort(403, 'Unauthorized access');
        }

        $userRole = $user->role->name;

        // Check if user has any of the allowed roles
        foreach ($roles as $role) {
            if ($userRole === $role) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized access');
    }
}