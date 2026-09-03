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
