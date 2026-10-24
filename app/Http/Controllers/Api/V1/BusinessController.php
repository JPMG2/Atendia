<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi negocio" for the app: how finished the profile is and one line per
 * section, from the same pieces the web reads. Read only for now: each section
 * gets its own edit when its screen is built, over the same Action the web uses.
 */
class BusinessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $client = Client::for($request->user());

        // EnsureAppAccess already refused anyone without a business.
        abort_if($client->businessProfile === null, 404);

        return response()->json(['data' => [
            'copy' => [
                'title' => __('client.business.title'),
                'sub' => __('client.business.sub'),
                'empty' => __('client.business.summary.empty'),
            ],
            'strength' => $client->businessProfile->meter($client->profileStrength),
            'sections' => $client->businessProfile->sections,
        ]]);
    }
}
