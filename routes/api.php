<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
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

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Públicas
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // Protegidas por token Bearer
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
