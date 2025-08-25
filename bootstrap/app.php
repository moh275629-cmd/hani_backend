<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.rate.limit' => \App\Http\Middleware\ApiRateLimit::class,
            'api.response' => \App\Http\Middleware\ApiResponseMiddleware::class,
            'encrypt.data' => \App\Http\Middleware\EncryptRequestData::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'global.admin' => \App\Http\Middleware\GlobalAdminMiddleware::class,
        ]);
        
        $middleware->group('api', [
            \App\Http\Middleware\ApiResponseMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
