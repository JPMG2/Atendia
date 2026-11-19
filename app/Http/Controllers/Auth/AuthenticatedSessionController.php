<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\DeviceChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view. The lost-phone way in marks its visit with
     * `?robo=1`; reaching the login any other way drops that destination,
     * so neither its notice nor its detour outlives the visit.
     */
    public function create(Request $request): View
    {
        $lostPhoneEntry = session('url.intended') === route('lost-phone');

        if ($lostPhoneEntry && ! $request->boolean('robo')) {
            session()->forget('url.intended');
            $lostPhoneEntry = false;
        }

        return view('auth.login', ['lostPhoneEntry' => $lostPhoneEntry]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (($user = $this->userOnUnknownDevice($request)) !== null) {
            DeviceChallenge::start($user, $request->boolean('remember'));

            return redirect()->route('device.challenge');
        }

        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($request->user()->panelHome());
    }

    /**
     * The e-mail code gate: right credentials from a browser the account has
     * never seen stop HERE, before any session opens. Wrong credentials fall
     * through to authenticate(), which throttles and answers as always.
     */
    private function userOnUnknownDevice(LoginRequest $request): ?User
    {
        if (! Auth::guard('web')->validate($request->only('email', 'password'))) {
            return null;
        }

        /** @var User $user */
        $user = Auth::guard('web')->getLastAttempted();

        return $user->deviceIsUnknown($request->userAgent()) ? $user : null;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
