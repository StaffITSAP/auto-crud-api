<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: __DIR__ . '/../routes/health.php',
        api: __DIR__ . '/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias middleware spatie
        $middleware->alias([
            'role'               => \Spatie\Permission\Middlewares\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withProviders([
        App\Providers\AutoCrudServiceProvider::class,   
        App\Providers\ConsoleTapServiceProvider::class, 
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
