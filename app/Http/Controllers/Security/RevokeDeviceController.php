<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\LoginDevice;
use Illuminate\View\View;

/**
 * The "this wasn't me" button of the security mail: a signed link that
 * revokes the device without asking for a login — the victim may not be
 * signed in anywhere, and the intruder holds the only live session.
 */
class RevokeDeviceController extends Controller
{
    public function __invoke(int $device): View
    {
        // A second click finds nothing and that is fine: the page reassures
        // either way instead of throwing a 404 at a worried user.
        LoginDevice::query()->whereKey($device)->delete();

        return view('security.device-revoked');
    }
}
