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
        return response()->json(['data' => [
            'name' => Company::brand(),
            // The words the phone used to keep its own copy of, read from the web's text in the
            // caller's variant: a change on the web reaches the app without a new install.
            'copy' => collect((array) config('atendia.app.copy'))->map(fn (string $key): string => __($key))->all(),
        ]]);
    }
}
