<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The country list a phone field opens, the very one the web's
 * `<x-inputsform.phone>` gets (active countries: flag, dial code, name), and
 * the business's own country first chosen, as the web preselects it.
 */
class PhoneCountriesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'countries' => Country::phoneFlags(states: [true]),
            'default' => Country::dialCode($request->user()->business?->country_id),
            'search' => __('forms.phone.search'),
        ]]);
    }
}
