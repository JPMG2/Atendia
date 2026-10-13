<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = (string) $request->string('email');

        // The same answer whether or not the address has an account: a different
        // one lets anyone list who our customers are. It also leaves after the
        // response, so how long it took cannot tell either.
        defer(fn () => Password::sendResetLink(['email' => $email]));

        return back()->with('status', __('passwords.requested'));
    }
}
