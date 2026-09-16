<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DeviceChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The screen where a login from an unknown device types the e-mailed code.
 * Only the challenge in session gets here; without one the screen bounces
 * back to the login instead of dangling empty.
 */
class DeviceChallengeController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! DeviceChallenge::pending()) {
            return redirect()->route('login');
        }

        return view('auth.device-challenge', [
            'maskedEmail' => DeviceChallenge::challengedUser()?->maskedEmail(),
        ]);
    }

    public function resend(): RedirectResponse
    {
        if (! DeviceChallenge::resend()) {
            return redirect()->route('login');
        }

        return back()->with('status', __('security.challenge.resent'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        if (! DeviceChallenge::pending()) {
            return redirect()->route('login');
        }

        $granted = DeviceChallenge::verify($request->string('code')->toString());

        if ($granted === null) {
            return back()->withErrors(['code' => __('security.challenge.wrong_code')]);
        }

        // Only now does the session open; the Login event takes over and
        // records the device plus the new-device alert with its kill switch.
        $user = Auth::guard('web')->loginUsingId($granted['user_id'], $granted['remember']);

        $request->session()->regenerate();

        return redirect()->intended($user->panelHome());
    }
}
