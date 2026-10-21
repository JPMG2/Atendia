<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\IssueAppToken;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAppAccess;
use App\Http\Requests\Api\V1\ChallengeCodeRequest;
use App\Http\Requests\Api\V1\ChallengeRecoveryRequest;
use App\Http\Requests\Api\V1\ChallengeResendRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MobileDeviceChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The second half of a sign-in from an install the account never used. An
 * expired, unknown or foreign challenge answers the same as a wrong code, so
 * nothing here tells a guesser which one it hit.
 */
class DeviceChallengeController extends Controller
{
    public function __construct(private readonly IssueAppToken $issueToken) {}

    /**
     * @throws ValidationException
     */
    public function verify(ChallengeCodeRequest $request): JsonResponse
    {
        $granted = MobileDeviceChallenge::verify(
            (string) $request->validated('challenge_id'),
            (string) $request->validated('code'),
            $request->deviceId(),
        );

        return $this->signIn($granted, 'code', __('security.challenge.wrong_code'));
    }

    /**
     * @throws ValidationException
     */
    public function recovery(ChallengeRecoveryRequest $request): JsonResponse
    {
        $granted = MobileDeviceChallenge::verifyRecovery(
            (string) $request->validated('challenge_id'),
            (string) $request->validated('recovery_code'),
            $request->deviceId(),
        );

        return $this->signIn($granted, 'recovery_code', __('security.challenge.wrong_recovery'));
    }

    /**
     * @throws ValidationException
     */
    public function resend(ChallengeResendRequest $request): JsonResponse
    {
        $challenge = MobileDeviceChallenge::resend((string) $request->validated('challenge_id'), $request->deviceId());

        if ($challenge === null) {
            throw ValidationException::withMessages(['challenge_id' => __('api.challenge_expired')]);
        }

        return response()->json(['challenge' => $challenge]);
    }

    /**
     * @param  array{user: User, device_id: string, device_name: string}|null  $granted
     *
     * @throws ValidationException
     */
    private function signIn(?array $granted, string $field, string $message): JsonResponse
    {
        if ($granted === null) {
            throw ValidationException::withMessages([$field => $message]);
        }

        // The plan may have lapsed between the password and the code.
        $access = $granted['user']->appAccess();

        if (! $access->isAllowed()) {
            return EnsureAppAccess::denial($access);
        }

        $token = $this->issueToken->handle($granted['user'], $granted['device_id'], $granted['device_name']);

        return UserResource::make($granted['user'])
            ->additional(['token' => $token])
            ->response();
    }
}
