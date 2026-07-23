<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
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
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());
        Blaze::optimize()->in(resource_path('views/components'));
    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction(),
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
