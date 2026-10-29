<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\CompanyProfile;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * The company's own data as the web's form words it, to look at, with the step of the web form that edits
 * each block (`AdminCompanyEditController`). The key is the one the web screen asks for.
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
            // Which of the web form's two steps edits each block; the phone opens that step's form.
            // The second step opens only once the first has created the company, as the web's stepper does.
            'edit' => [
                'label' => __('company.edit'),
                'steps' => [
                    'identity' => 'main', 'address' => 'main', 'tax' => 'main', 'footer' => 'main',
                    ...(Company::query()->exists() ? ['contact' => 'commercial', 'social' => 'commercial'] : []),
                ],
            ],
        ]]);
    }
}
