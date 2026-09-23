<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Classes\Main\Client;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * "This wasn't me" in the notice mailed to the current address: stops the
 * pending change before a hijacker can confirm it, no login needed.
 */
class CancelEmailChangeController extends Controller
{
    public function __invoke(User $user, string $hash): View
    {
        // A second click finds nothing pending and still reassures.
        Client::for($user)->account->cancelEmailChange($hash);

        return view('settings.link-result', ['state' => 'cancelled']);
    }
}
