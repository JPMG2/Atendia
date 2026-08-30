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
        // SINGLETON, not optional: the isolation scope and whoever adopts a
        // business must look at the SAME instance. Otherwise the business set is
        // lost, the filter never applies, and every business's data is readable.
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
        // The working data lives in 'atendia' even though APP_ENV is 'local', so
        // gating on isProduction() would leave it UNPROTECTED. Blocked always,
        // except when the active database is exactly the testing one.
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
     * Super-admin: the "admin" role passes any gate or policy. That is what
     * lets the admin reach the client panel too.
     */
    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
    }

    /**
     * On the requests that follow, Livewire does not re-apply the original
     * route's middleware — only the ones on its allowlist, and spatie's are not
     * there. Without this a revoked permission would keep passing until the
     * page is reloaded. See .ai/guidelines/arquitectura-paneles.md.
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
        // Stray HTTP requests are only blocked while testing.
        if (app()->environment('testing')) {
            Http::preventStrayRequests();
        }
    }

    /**
     * Rate limits for the API the mobile app consumes.
     */
    private function configureRateLimiting(): void
    {
        // General: 60 req/min per authenticated user, or per IP when anonymous.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Login and register: strict, to slow brute force down (email + IP).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(6)
            ->by(((string) $request->input('email')).'|'.$request->ip()));
    }
}
