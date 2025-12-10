<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\PowerBIApiAuth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $RoleMiddleware = class_exists(\Spatie\Permission\Middlewares\RoleMiddleware::class)
            ? \Spatie\Permission\Middlewares\RoleMiddleware::class
            : \Spatie\Permission\Middleware\RoleMiddleware::class;

        $PermissionMiddleware = class_exists(\Spatie\Permission\Middlewares\PermissionMiddleware::class)
            ? \Spatie\Permission\Middlewares\PermissionMiddleware::class
            : \Spatie\Permission\Middleware\PermissionMiddleware::class;

        $RoleOrPermission = class_exists(\Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class)
            ? \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class
            : \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class;

        $middleware->alias([
            'role'               => $RoleMiddleware,
            'permission'         => $PermissionMiddleware,
            'role_or_permission' => $RoleOrPermission,

            // 👇 NEW: our Power BI API middleware alias
            'powerbi'            => PowerBIApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
