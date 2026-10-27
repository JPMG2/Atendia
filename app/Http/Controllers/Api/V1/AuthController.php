<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\IssueAppToken;
use App\Actions\Admin\CheckAppBrand;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAppAccess;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginDevice;
use App\Services\MobileDeviceChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Mobile app authentication through Sanctum bearer tokens.
 */
class AuthController extends Controller
{
    public function __construct(private readonly IssueAppToken $issueToken) {}

    /**
     * Checks the credentials and that the plan opens the app, before any code
     * is sent or device recorded. An install the account never used gets no
     * token yet: a code goes to its e-mail or WhatsApp and the app answers it
     * at `login/challenge`, exactly like the web's unknown-device gate.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticatedUser();

        // The platform's people have no plan to check, but hold the keys to every business:
        // they answer a code on EVERY sign-in, from an install they know or not.
        if ($user->isStaff()) {
            return response()->json(['challenge' => MobileDeviceChallenge::start($user, $request->deviceId(), $request->deviceName())], 202);
        }

        $access = $user->appAccess();

        if (! $access->isAllowed()) {
            return EnsureAppAccess::denial($access);
        }

        if ($user->appDeviceIsUnknown($request->deviceId())) {
            $challenge = MobileDeviceChallenge::start($user, $request->deviceId(), $request->deviceName());

            return response()->json(['challenge' => $challenge], 202);
        }

        $token = $this->issueToken->handle($user, $request->deviceId(), $request->deviceName());

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response();
    }

    /**
     * Revokes only the token this request was made with.
     */
    public function logout(Request $request): JsonResponse
    {
        // A phone that signed out stops receiving this account's news right away.
        $request->user()->loginDevices()
            ->where('fingerprint', LoginDevice::fingerprintForApp((string) $request->header('X-Device-Id')))
            ->first()?->clearPushToken();

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Sesión cerrada correctamente.')]);
    }

    public function me(Request $request, CheckAppBrand $brand): UserResource
    {
        // The app opens through here: it is where an install tells which name it was built with.
        $brand->handle($request->header('X-App-Brand'));

        return UserResource::make($request->user());
    }
}
