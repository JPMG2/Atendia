<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\PaymentReceiptController;
use App\Http\Controllers\Conversations\MessageMediaController;
use App\Http\Controllers\DemoChatController;
use App\Http\Controllers\Reports\ShowReportController;
use App\Http\Controllers\Security\RevokeDeviceController;
use App\Http\Controllers\Settings\CancelEmailChangeController;
use App\Http\Controllers\Settings\ConfirmEmailChangeController;
use App\Http\Controllers\Settings\RestoreAccountController;
use App\Http\Controllers\Settings\VerifyAccountEmailController;
use App\Models\Business;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

// The hero's interactive demo: throttled hard — every reply costs tokens.
Route::post('/demo/mensaje', DemoChatController::class)
    ->middleware('throttle:6,1')
    ->name('demo.message');

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
    ->middleware('signed:relative')
    ->name('security.devices.revoke');

// Agents only work the inbox: their home IS the conversations.
Route::get('/dashboard', fn () => auth()->user()?->isAgent() ? redirect()->route('conversations') : view('dashboard'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('dashboard');

// "Mi negocio": living mock-up of the business profile, same lock as the panel.
Route::get('/negocio', fn () => view('my-business'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-business');

// One deep link per profile section (LinkedIn-style "update just this piece"):
// same screen, one card. Named one by one because the menu resolves bare
// route names, and the literal slug is what keeps the component lookup safe.
foreach (['identidad', 'ubicacion', 'horarios', 'turnos', 'contacto', 'redes', 'facturacion'] as $slug) {
    Route::get("/negocio/{$slug}", fn () => view('my-business', ['section' => $slug]))
        ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
        ->name("my-business.{$slug}");
}

// The public booking link: no session, so it carries its own throttle. The
// code is the only key; a wrong one is a dead link, never a listing.
Route::get('/reservar/{code}', fn (string $code) => view('booking', ['code' => $code]))
    ->middleware('throttle:20,1')
    ->name('booking.public');

// "Agenda": the day's bookings and the free hours the assistant offers.
Route::get('/agenda', fn () => view('agenda'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('agenda');

// Services and products of the business: living mock-ups behind the same lock.
Route::get('/servicios', fn () => view('my-services'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-services');

Route::get('/productos', fn () => view('my-products'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-products');

// Linking the WhatsApp number the assistant answers through.
Route::get('/whatsapp', fn () => view('whatsapp'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('whatsapp');

// "Lo que sabe tu asistente": the knowledge base, visible and teachable.
Route::get('/asistente', fn () => view('assistant'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('assistant');

// The assistant's behaviour: the handoff dial and the owner's own cases.
Route::get('/asistente/configuracion', fn () => view('assistant-settings'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('assistant.settings');

// Every thread the assistant holds with the business's customers.
Route::get('/conversaciones', fn () => view('conversations'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('conversations');

Route::get('/conversaciones/mensajes/{message}/adjunto/{index}', MessageMediaController::class)
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->whereNumber('index')
    ->name('conversations.media');

// "Mis clientes": the directory every conversation feeds.
Route::get('/clientes', fn () => view('customers'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('customers');

// "Mis estadísticas": what the assistant handled, read at the plan's depth.
Route::get('/estadisticas', fn () => view('statistics'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('statistics');

// The subscription: entitlements, usage meters and the ladder with padlocks.
// Every Imprimir / Excel / CSV button: the report class holds the lock (admin or client).
Route::get('/reportes/{report}/{format}', ShowReportController::class)
    ->middleware(['auth', 'verified'])
    ->name('reports.show');

Route::get('/plan', fn () => view('plan'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-plan');

// "Mis pagos": the running period, the next payment and every payment made.
Route::get('/pagos', fn () => view('payments'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-payments');

Route::get('/pagos/{payment}/comprobante', PaymentReceiptController::class)
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('my-payments.receipt');

// The invitation's landing: public, since the invitee has no account yet. The
// token is the key (only its hash is stored) and it expires on its own.
Route::get('/equipo/unirme/{token}', fn (string $token) => view('team-join', ['token' => $token]))
    ->middleware(['guest', 'throttle:20,1'])
    ->name('team.join');

// "Equipo": the people behind the assistant and the departments a handoff lands in.
Route::get('/equipo', fn () => view('team'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('team');

// Help: the answers before a ticket. Reachable by an invited agent too — they
// hit the same problems and the articles hold no business data.
Route::get('/ayuda', fn () => view('help'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('help');

// "Gana con AtendIa": the client's referral link and its tally.
Route::get('/gana', fn () => view('referrals'))
    ->middleware(['auth', 'verified', 'permission:access-client-app', 'permission:manage-business'])
    ->name('referrals');

// Client onboarding wizard. It writes real data now, so it sits behind the
// client-panel lock. No 'verified': the welcome tour must not wait for the
// verification mail.
Route::livewire('/alta', 'business.wizard')
    ->middleware(['auth', 'permission:access-client-app', 'permission:manage-business'])
    ->name('onboarding');

// "Ajustes": the person behind the business — name, login email, password,
// devices and closing the account. Same lock as every client screen.
Route::get('/ajustes', fn () => view('settings'))
    ->middleware(['auth', 'verified', 'permission:access-client-app'])
    ->name('settings');

// One deep link per card, the same pattern as "Mi negocio".
foreach (['perfil', 'correo', 'contrasena', 'dos-pasos', 'dispositivos', 'avisos', 'actividad', 'cuenta'] as $slug) {
    Route::get("/ajustes/{$slug}", fn () => view('settings', ['section' => $slug]))
        ->middleware(['auth', 'verified', 'permission:access-client-app'])
        ->name("settings.{$slug}");
}

// Breeze's old address keeps working for bookmarks and old mails.
Route::redirect('/profile', '/ajustes', 301);

// The account links mailed by the settings screen. Signed and without a
// login on purpose (see each controller); throttled against link guessing.
Route::middleware(['signed:relative', 'throttle:6,1'])->group(function (): void {
    Route::get('/ajustes/correo/confirmar/{user}/{hash}', ConfirmEmailChangeController::class)
        ->name('settings.email.confirm');
    Route::get('/ajustes/correo/cancelar/{user}/{hash}', CancelEmailChangeController::class)
        ->name('settings.email.cancel');
    Route::get('/ajustes/correo/verificar/{user}/{hash}', VerifyAccountEmailController::class)
        ->name('settings.email.verify');
    Route::get('/cuenta/restaurar/{user}', RestoreAccountController::class)
        ->withTrashed()
        ->name('account.restore');
});

require __DIR__.'/auth.php';
