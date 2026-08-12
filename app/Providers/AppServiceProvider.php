<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        // SINGLETON obligatorio: el scope de aislamiento y quien adopta un negocio
        // (jobs, KnowledgeRetriever) tienen que estar mirando la MISMA instancia.
        // Sin esto cada app(Tenant::class) devuelve una nueva, el negocio fijado se
        // pierde y el filtro no se aplica: se leen datos de todos los negocios.
        $this->app->singleton(Tenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureModels();
        $this->configureDates();
        $this->configureRequests();
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureLivewire();
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());
        Blaze::optimize()->in(resource_path('views/components'));
    }

    private function configureCommands(): void
    {
        // El dato de trabajo vive en 'atendia' AUNQUE APP_ENV sea 'local' (no 'production'),
        // así que gatear por isProduction() dejaría 'atendia' DESPROTEGIDA. Bloqueamos los
        // comandos destructivos (migrate:fresh/refresh/reset, db:wipe) SIEMPRE, salvo cuando
        // la base activa es EXACTAMENTE la de testing. Esto cubre el CLI directo Y el
        // RefreshDatabase (que corre migrate:fresh por dentro), en Unit o Feature, extienda
        // o no el guard de TestCase. Ver .ai/guidelines/migraciones-seguras.md.
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        DB::prohibitDestructiveCommands(
            $database !== 'atendia_testing',
        );
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict();
    }

    /**
     * Super-admin: el rol "admin" pasa cualquier gate/policy. Es lo que permite
     * que el admin acceda también al panel cliente (ver arquitectura de paneles).
     */
    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
    }

    /**
     * En los requests que siguen (POST /livewire/update) Livewire NO re-aplica el
     * middleware de la ruta original: solo el de su lista blanca (Authenticate,
     * Authorize…). Los de spatie no están, así que sin esto un permiso revocado
     * seguiría pasando hasta que el usuario recargue la página.
     *
     * Ver .ai/guidelines/arquitectura-paneles.md (la cerradura va en middleware/policy).
     */
    private function configureLivewire(): void
    {
        Livewire::addPersistentMiddleware([
            PermissionMiddleware::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureRequests(): void
    {
        // Solo prevenir peticiones HTTP en testing
        if (app()->environment('testing')) {
            Http::preventStrayRequests();
        }
    }

    /**
     * Límites de tasa para la API consumida por la app móvil.
     */
    private function configureRateLimiting(): void
    {
        // General: 60 req/min por usuario autenticado (o por IP si es anónimo).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Login/registro: estricto para frenar fuerza bruta (por email + IP).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(6)
            ->by(((string) $request->input('email')).'|'.$request->ip()));
    }
}
