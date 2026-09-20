<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Security\RevokeDeviceController;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', fn () => view('welcome'));

// The referral code rides session + 30-day cookie (industry window). A real
// code earns the personal invite page — a named invite converts, a cold form
// does not (Dropbox pattern); a dead code falls through to plain register.
Route::get('/r/{code}', function (string $code) {
    $inviter = Business::query()->where('referral_code', $code)->first();

    if ($inviter === null) {
        return redirect()->route('register');
    }

    session()->put('atendia_ref', $code);
    Cookie::queue('atendia_ref', $code, 60 * 24 * 30);

    return view('referral-invite', ['inviter' => $inviter->name]);
})->name('referral.landing');

Route::get('/idioma/{locale}', function (string $locale) {
    if (in_array($locale, config('locales.supported'), true)) {
        session()->put('locale', $locale);
    }

    return back();
})->name('locale.switch');

// "This wasn't me" in the new-device mail. Signed and unauthenticated on
// purpose: the victim may hold no session while the intruder holds the only
// live one. Worst misuse of a leaked link is kicking that device out.
Route::get('/seguridad/dispositivos/{device}/cerrar', RevokeDeviceController::class)
    ->middleware('signed')
    ->name('security.devices.revoke');

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

// Linking the WhatsApp number the assistant answers through.
Route::get('/whatsapp', fn () => view('whatsapp'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('whatsapp');

// Every thread the assistant holds with the business's customers.
Route::get('/conversaciones', fn () => view('conversations'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('conversations');

// "Mis clientes": the directory every conversation feeds.
Route::get('/clientes', fn () => view('customers'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('customers');

// "Mis estadísticas": what the assistant handled, read at the plan's depth.
Route::get('/estadisticas', fn () => view('statistics'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('statistics');

// The subscription: entitlements, usage meters and the ladder with padlocks.
Route::get('/plan', fn () => view('plan'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('my-plan');

// "Gana con AtendIa": the client's referral link and its tally.
Route::get('/gana', fn () => view('referrals'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('referrals');

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
