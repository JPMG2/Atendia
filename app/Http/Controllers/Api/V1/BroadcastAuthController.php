<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Signs a phone's subscription to its business channel. The web does this with
 * a session cookie; the phone has only a bearer token, so it asks here and the
 * answer is the same one `routes/channels.php` gives the web.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): mixed
    {
        // Only the business channel: the phone has no use for any other, and a
        // wider door would hand it every channel the web happens to define.
        $request->validate([
            // The D flag: without it `$` also matches before a trailing newline,
            // and the signature would cover a name the callback reads as another.
            'socket_id' => ['required', 'string', 'regex:/^\d{1,20}\.\d{1,20}$/D'],
            'channel_name' => ['required', 'string', 'regex:/^private-business\.[1-9][0-9]{0,17}$/D'],
        ]);

        // A callback that says no makes this answer 403, with nothing signed.
        return Broadcast::auth($request);
    }
}
