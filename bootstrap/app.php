<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
