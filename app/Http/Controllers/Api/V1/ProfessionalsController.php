<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Profesionales" for the app, read-only: the phone looks at who attends the
 * bookings and nothing more. Creating, editing and removing them is the web's
 * (the owner's setup), so no write route exists here. The business is the caller's.
 */
class ProfessionalsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $professionals = Client::for($request->user())->professionals;

        abort_if($professionals === null, 404);

        return response()->json(['data' => $professionals->overview]);
    }
}
