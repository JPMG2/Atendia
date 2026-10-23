<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeviceChallengeController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\WhatsAppStatusController;
use App\Http\Controllers\Webhooks\EvolutionWebhookController;
use App\Http\Middleware\EnsureAppAccess;
use App\Http\Middleware\RejectRevokedDevice;
use App\Http\Middleware\SetTenantDatabaseContext;
use App\Http\Middleware\VerifyEvolutionWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Canal de la app móvil. Autenticación con tokens Bearer de Sanctum.
| Todo va versionado bajo /api/v1 para poder evolucionar sin romper
| clientes viejos. El límite general 'api' aplica a todo el grupo; las
| rutas de auth suman 'throttle:auth' (estricto, anti fuerza bruta).
|
*/

// Evolution delivers here over the internal Docker network. No throttle: a
// busy WhatsApp must never have messages dropped by a rate limiter, and the
// shared-secret middleware is the lock.
Route::post('webhooks/evolution', EvolutionWebhookController::class)
    ->middleware(VerifyEvolutionWebhook::class);

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Public.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // The second half of a sign-in from an install the account never used.
    Route::prefix('login/challenge')->middleware('throttle:app-challenge')->group(function (): void {
        Route::post('/', [DeviceChallengeController::class, 'verify']);
        Route::post('recovery', [DeviceChallengeController::class, 'recovery']);
        Route::post('resend', [DeviceChallengeController::class, 'resend']);
    });

    // Behind a bearer token. Authentication comes first, so the business
    // (Postgres row security) and the device check can read who is calling.
    Route::middleware(['auth:sanctum', SetTenantDatabaseContext::class, RejectRevokedDevice::class])->group(function (): void {
        // Signing out stays open to everyone: a lapsed plan must not trap a token.
        Route::post('logout', [AuthController::class, 'logout']);

        // Closing the account stays open too: leaving is a right a lapsed plan does not suspend.
        Route::delete('account', [AccountController::class, 'close'])->middleware('throttle:app-account');

        Route::middleware(EnsureAppAccess::class)->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::get('menu', MenuController::class);

            Route::get('account', [AccountController::class, 'show']);
            Route::post('account/password', [AccountController::class, 'password'])->middleware('throttle:app-account');
            Route::get('account/devices', [DeviceController::class, 'index']);
            Route::delete('account/devices/{device}', [DeviceController::class, 'destroy'])->where('device', '[0-9]{1,18}');
            Route::post('account/devices/revoke-others', [DeviceController::class, 'revokeOthers'])->middleware('throttle:app-account');
            Route::get('conversations', [ConversationController::class, 'index']);
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->where('conversation', '[0-9]{1,18}');
            Route::get('whatsapp', WhatsAppStatusController::class)->middleware('can:manage-business');
        });
    });
});
