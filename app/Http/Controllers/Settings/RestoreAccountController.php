<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Account\RestoreAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

/**
 * The way back in the goodbye mail. It restores but never signs in: a link
 * that opened a session would turn a leaked inbox into a login.
 */
class RestoreAccountController extends Controller
{
    public function __invoke(User $user): View
    {
        if (! $user->trashed()) {
            return view('settings.link-result', ['state' => 'restored']);
        }

        return view('settings.link-result', [
            'state' => app(RestoreAccount::class)->handle($user) ? 'restored' : 'restore_expired',
        ]);
    }
}
