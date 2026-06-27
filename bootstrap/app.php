<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Panel admin: área propia, protegida por permiso de área.
            Route::middleware(['web', 'auth', 'verified', 'permission:access-admin-panel'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en el proxy de EasyPanel/Traefik: respeta X-Forwarded-Proto
        // para que asset()/url() generen http o https segun como entre el usuario
        // (evita "contenido mixto" que rompia los estilos al entrar por https).
        $middleware->trustProxies(at: '*');

        // Resuelve el locale (sesión › geolocalización › default) en cada request web.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Aliases de spatie/laravel-permission para usar en rutas.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
