<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de la app móvil vía tokens Bearer de Sanctum.
 */
class AuthController extends Controller
{
    /**
     * Registra un usuario y devuelve su primer token de acceso.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $token = $user->createToken($request->deviceName())->plainTextToken;

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Valida credenciales y emite un nuevo token de acceso.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($request->deviceName())->plainTextToken;

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response();
    }

    /**
     * Revoca únicamente el token con el que se hizo esta petición.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Sesión cerrada correctamente.')]);
    }

    /**
     * Devuelve el usuario autenticado.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
