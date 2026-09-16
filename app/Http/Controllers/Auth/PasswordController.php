<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // A fresh password is the moment a takeover gets evicted: on request,
        // every session but this one dies with its device row.
        if ($request->boolean('logout_others')) {
            $request->user()->revokeOtherDevices(LoginDevice::fingerprintFor($request->userAgent()));
        }

        return back()->with('status', 'password-updated');
    }
}
