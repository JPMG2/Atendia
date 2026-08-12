<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales de broadcast
|--------------------------------------------------------------------------
|
| OJO: estas funciones NO son gates. El `Gate::before` que hace pasar al rol
| admin por cualquier policy no interviene acá, así que el dueño se contempla
| explícito o se queda afuera de sus propios canales.
|
*/

// OJO con el tipo: el parámetro sale del NOMBRE del canal, así que llega string
// ("1"). Con `declare(strict_types=1)` un `int $businessId` tira TypeError, el
// broadcaster lo traga y responde 403 — nadie entra a ningún canal y no se ve
// el error por ningún lado.
Broadcast::channel('business.{businessId}', function (User $user, int|string $businessId): bool {
    $businessId = (int) $businessId;

    // El dueño de AtendIa no pertenece a ningún negocio (business_id null) y
    // puede escuchar cualquiera, que es lo que le permite dar soporte. Un
    // cliente, únicamente el suyo.
    if ($user->business_id === null) {
        return $user->hasRole('admin');
    }

    return $user->business_id === $businessId;
});
