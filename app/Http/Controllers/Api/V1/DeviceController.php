<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmPasswordRequest;
use App\Models\LoginDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The web's "Dispositivos conectados" card: the list, one remote sign-out, and all the others. */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $current = $this->currentFingerprint($request);

        return response()->json(['data' => $request->user()->recentDevices()->map(fn (LoginDevice $device): array => [
            'id' => $device->id,
            'label' => $device->label(),
            'location' => $device->location,
            'last_login_at' => $device->last_login_at,
            'current' => $device->fingerprint === $current,
        ])->values()]);
    }

    public function destroy(Request $request, int $device): JsonResponse
    {
        // Scoped to the caller: another person's id is simply not found.
        $row = $request->user()->loginDevices()->findOrFail($device);

        // Signing out the install in use would strand the very screen asking: that is `logout`.
        if ($row->fingerprint === $this->currentFingerprint($request)) {
            return response()->json(['message' => __('profile.devices.current_refused')], 422);
        }

        // The row going is what kills a token on its next call; dropping the token too makes it immediate.
        $request->user()->tokens()->where('name', $row->fingerprint)->delete();
        $row->delete();

        return response()->json(['message' => __('profile.devices.revoked')]);
    }

    public function revokeOthers(ConfirmPasswordRequest $request): JsonResponse
    {
        $request->confirmPassword();

        $current = $this->currentFingerprint($request);
        $user = $request->user();

        $user->tokens()->where('name', '!=', $current)->delete();
        $user->revokeOtherDevices($current);

        return response()->json(['message' => __('profile.devices.revoke_all_done')]);
    }

    private function currentFingerprint(Request $request): string
    {
        return LoginDevice::fingerprintForApp((string) $request->header('X-Device-Id'));
    }
}
