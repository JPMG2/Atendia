<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * The platform's name for the phone, public because the sign-in screen is drawn
 * before anyone is signed in. It is the row the web reads, so a rename is one edit.
 */
class BrandController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => ['name' => Company::brand()]]);
    }
}
