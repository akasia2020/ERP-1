<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect('/');
        }

        $user = auth()->user();

        if (!$user->role) {
            abort(403, 'Access Denied');
        }

        $userRole = $user->role->name;

        if (!in_array($userRole, $roles)) {
            abort(403, 'Access Denied');
        }

        return $next($request);
    }
}