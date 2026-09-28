<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isOwnerAdmin()) {
            abort(403, 'Chỉ quản trị cửa hàng mới vào được khu này.');
        }

        return $next($request);
    }
}
