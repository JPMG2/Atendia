<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * The button of the verification mail. Signed and without a login, like the
 * email-change confirmation: the inbox is often read on another device, or
 * one signed in as someone else, and the link itself proves the address.
 */
class VerifyAccountEmailController extends Controller
{
    public function __invoke(User $user, string $hash): View
    {
        return view('settings.link-result', [
            'state' => Client::for($user)->account->verifyEmail($hash) ? 'verified' : 'invalid',
            'email' => $user->email,
        ]);
    }
}
