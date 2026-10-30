<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PushTokenRequest;
use App\Models\LoginDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Where the server may buzz THIS install: one token per phone, kept on its device row. */
class PushTokenController extends Controller
{
    public function update(PushTokenRequest $request): JsonResponse
    {
        $this->currentDevice($request)->registerPushToken((string) $request->validated('token'));

        return response()->json(['message' => __('api.push.registered')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->currentDevice($request)->clearPushToken();

        return response()->json(['message' => __('api.push.removed')]);
    }

    /** Scoped to the caller: a device row of another person is never reachable from here. */
    private function currentDevice(Request $request): LoginDevice
    {
        return $request->user()->appDeviceOrFail((string) $request->header('X-Device-Id'));
    }
}
