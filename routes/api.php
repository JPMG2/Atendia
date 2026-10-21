<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceChallengeController;
use App\Http\Controllers\Webhooks\EvolutionWebhookController;
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
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:app-register');
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
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
