<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', fn () => view('welcome'));

Route::get('/idioma/{locale}', function (string $locale) {
    if (in_array($locale, config('locales.supported'), true)) {
        session()->put('locale', $locale);
    }

    return back();
})->name('locale.switch');

Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('dashboard');

// "Mi negocio": living mock-up of the business profile, same lock as the panel.
Route::get('/negocio', fn () => view('my-business'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('my-business');

// One deep link per profile section (LinkedIn-style "update just this piece"):
// same screen, one card. Named one by one because the menu resolves bare
// route names, and the literal slug is what keeps the component lookup safe.
foreach (['identidad', 'ubicacion', 'horarios', 'contacto', 'redes', 'facturacion'] as $slug) {
    Route::get("/negocio/{$slug}", fn () => view('my-business', ['section' => $slug]))
        ->middleware(['auth', 'verified', 'permission:access-client-app'])
        ->name("my-business.{$slug}");
}

// Services and products of the business: living mock-ups behind the same lock.
Route::get('/servicios', fn () => view('my-services'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('my-services');

Route::get('/productos', fn () => view('my-products'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('my-products');

// Client onboarding wizard. It writes real data now, so it sits behind the
// client-panel lock. No 'verified': the welcome tour must not wait for the
// verification mail.
Route::livewire('/alta', 'business.wizard')
    ->middleware(['auth', 'permission:access-client-app'])
    ->name('onboarding');

// TEMPORARY, local only: walk the wizard without registering — signs in a
// throwaway demo client. Listed in aproduccion.md to be deleted at go-live.
if (app()->environment('local')) {
    Route::get('/alta-demo', function () {
        // Every visit starts a pristine run: the wizard now persists and
        // preloads on re-entry, so the previous demo tenant must leave whole
        // (every business_id FK cascades — the demo user included).
        User::query()->where('email', 'demo@atendia.test')->first()?->business?->forceDelete();

        $demo = User::query()->firstOrCreate(
            ['email' => 'demo@atendia.test'],
            ['name' => 'Demo AtendIa', 'password' => Hash::make(Str::random(32))],
        );

        $demo->assignRole('client');

        Auth::login($demo);

        return redirect()->route('onboarding');
    })->name('onboarding.demo');
}

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
