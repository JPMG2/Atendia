<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the platform's staff token belongs to, and how long it may sit idle.
 * It is the first door behind the staff lock: the screens of the Admin hang from here.
 */
class AdminSessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return UserResource::make($request->user())
            ->additional(['idle_minutes' => (int) config('atendia.security.staff_idle_minutes')])
            ->response();
    }
}
