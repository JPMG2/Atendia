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

// Mind the type: the parameter comes from the channel NAME, so it arrives as
// a string. Under strict_types an `int` throws a TypeError, the broadcaster
// swallows it and answers 403 — nobody joins and the error shows nowhere.
Broadcast::channel('business.{businessId}', function (User $user, int|string $businessId): bool {
    $businessId = (int) $businessId;

    // AtendIa's owner belongs to no business and may listen to any, which is
    // what lets them support a customer. A client hears only its own.
    if ($user->business_id === null) {
        return $user->hasRole('admin');
    }

    return $user->business_id === $businessId;
});
