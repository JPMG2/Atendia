<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * The button of the confirmation mail. Signed and without a login on
 * purpose: the new inbox is often opened on another device, and the link
 * itself is the proof the address belongs to the owner.
 */
class ConfirmEmailChangeController extends Controller
{
    public function __invoke(User $user, string $hash): View
    {
        if (! Client::for($user)->account->confirmEmailChange($hash)) {
            return view('settings.link-result', ['state' => 'invalid']);
        }

        return view('settings.link-result', ['state' => 'confirmed', 'email' => $user->email]);
    }
}
