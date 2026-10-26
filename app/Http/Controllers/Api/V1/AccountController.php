<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\CloseAccount;
use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\CloseAccountRequest;
use App\Models\LoginDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The account actions of the web's settings page, through the same Actions:
 * what the app does here is what the web would have done, effects included.
 */
class AccountController extends Controller
{
    /** What the profile screen needs to draw only the controls the person may use. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'can_close' => ! $request->user()->isAgent(),
            'close_keyword' => __('settings.close.keyword'),
            'restore_days' => (int) config('atendia.account_restore_days'),
            // The row that opens the report sheet is named as the web's top-bar button is.
            'support_label' => __('support.open'),
        ]]);
    }

    public function password(ChangePasswordRequest $request): JsonResponse
    {
        $request->confirmPassword();

        Client::for($request->user())->account->changePassword(
            (string) $request->validated('password'),
            $request->logoutOthers(),
            LoginDevice::fingerprintForApp((string) $request->header('X-Device-Id')),
        );

        return response()->json(['message' => __($request->logoutOthers() ? 'settings.password.changed_and_closed' : 'settings.password.changed')]);
    }

    public function close(CloseAccountRequest $request): JsonResponse
    {
        $request->confirmPassword();

        app(CloseAccount::class)->handle($request->user());

        return response()->json(['message' => __('settings.close.done')]);
    }
}
