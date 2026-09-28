<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Chỉ nhân viên / quản trị mới vào được khu này.');
        }

        return $next($request);
    }
}
