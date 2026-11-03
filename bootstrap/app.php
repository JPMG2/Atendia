<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\ExpireIdleStaffSession;
use App\Http\Middleware\LogoutRevokedDevice;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\RequireSupportedAppBuild;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantDatabaseContext;
use App\Http\Middleware\ThrottleByAddressBucket;
use App\Http\Middleware\TrustCloudflareVisitorIp;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Panel admin: área propia, protegida por permiso de área.
            Route::middleware(['web', 'auth', 'verified', 'permission:access-admin-panel', 'staff.two-factor'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en el proxy de EasyPanel/Traefik: respeta X-Forwarded-Proto
        // para que asset()/url() generen http o https segun como entre el usuario
        // (evita "contenido mixto" que rompia los estilos al entrar por https).
        // Only the internal network is a proxy. With '*' Laravel believed the
        // FIRST address of X-Forwarded-For, which the visitor writes: Traefik
        // appends the real one at the end, so every per-IP limit was bypassable
        // by sending a made-up header. Traefik sits on a private address, which
        // is why the proto and host headers (https assets) keep being honoured.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1']);

        // After the proxy trust above: it reads what that trust makes of the address.
        $middleware->append(TrustCloudflareVisitorIp::class);

        // A signed-in user bounced off /login lands on their own panel, not
        // on the client dashboard every guest redirect pointed at.
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()?->panelHome() ?? route('dashboard', absolute: false));

        // The referral cookie is a public share code, nothing sensitive:
        // plain text keeps it readable across the signup flow and in tests.
        $middleware->encryptCookies(except: ['atendia_ref']);

        // Resuelve el locale (sesión › geolocalización › default) en cada request web.
        // The browser posts its policy reports with no session and no token.
        $middleware->validateCsrfTokens(except: [ltrim(ContentSecurityPolicy::REPORT_PATH, '/')]);

        $middleware->web(append: [
            ContentSecurityPolicy::class,
            SetLocale::class,
            SetTenantDatabaseContext::class,
            LogoutRevokedDevice::class,
            ExpireIdleStaffSession::class,
        ]);

        // The app has no session: its language comes from the phone's header.
        $middleware->api(append: [SetApiLocale::class]);

        // Laravel sorts the token check first; an outdated phone must hear "update" (in its language) even with a dead token.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetApiLocale::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RequireSupportedAppBuild::class);

        // Aliases de spatie/laravel-permission para usar en rutas.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'staff.two-factor' => RequireStaffTwoFactor::class,
            // Every `throttle:` counts an IPv6 visitor by its /64, not by an address it can rotate.
            'throttle' => ThrottleByAddressBucket::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // demo/* is the landing's fetch-only endpoint: its errors must come
        // back as JSON, never as a redirect the browser fetch would follow.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('demo/*'),
        );

        // The app prints the server's message as is: the framework's "Too Many
        // Attempts." and "Server Error" would reach the phone in English.
        // With debug on, a 500 keeps its trace for whoever is developing.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if (! $request->is('api/*') || ! $response instanceof JsonResponse) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 429) {
                return $response->setData(['message' => __('errors.too_many_requests.message'), 'code' => 'too_many_requests']);
            }

            if ($status >= 500 && ! config('app.debug')) {
                return $response->setData(['message' => __('errors.server_error.message'), 'code' => 'server_error']);
            }

            return $response;
        });
    })->create();
