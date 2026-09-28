<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureOwnerAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = getenv('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(at: $trustedProxies === '*'
                ? '*'
                : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        }
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'customer' => EnsureCustomer::class,
            'owner' => EnsureOwnerAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'thanh-toan/momo/ipn',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
