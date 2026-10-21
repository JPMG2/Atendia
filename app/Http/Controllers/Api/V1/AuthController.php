<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Account\IssueAppToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MobileDeviceChallenge;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Mobile app authentication through Sanctum bearer tokens.
 */
class AuthController extends Controller
{
    public function __construct(private readonly IssueAppToken $issueToken) {}

    /**
     * Registers a user, with the role the web sign-up gives, and hands back
     * their first access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $user->assignRole('client');

        // Same event as the web sign-up: the verification mail rides on it.
        event(new Registered($user));

        $token = $this->issueToken->handle($user, $request->deviceId(), $request->deviceName());

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Checks the credentials. An install the account never used gets no token
     * yet: a code goes to its e-mail or WhatsApp and the app answers it at
     * `login/challenge`, exactly like the web's unknown-device gate.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticatedUser();

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
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Sesión cerrada correctamente.')]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
