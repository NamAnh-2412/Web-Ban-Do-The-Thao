<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->isStoreAccount()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Tài khoản cửa hàng dùng khu Quản trị (bán / kho / đơn). Mua và thuê trên website dành cho tài khoản khách.');
        }

        return $next($request);
    }
}
