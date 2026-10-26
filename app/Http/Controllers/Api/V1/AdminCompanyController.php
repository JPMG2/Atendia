<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\CompanyProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The company's own data as the web's form words it, to look at. Read only: saving, the logo upload and
 * the social rows are the delivery of actions, and the key is the one the web screen asks for.
 */
class AdminCompanyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $profile = new CompanyProfile;

        return response()->json(['data' => [
            ...$profile->head,
            'missing' => CompanyProfile::MISSING,
            'sections' => $profile->sections,
            'logos' => $profile->logos,
            'social' => [...$profile->socialHead, 'rows' => $profile->social],
        ]]);
    }
}
