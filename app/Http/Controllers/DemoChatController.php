<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\AsistenteAtendia;
use App\Models\Business;
use App\Models\DemoMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The hero's interactive demo: the visitor writes as a customer and the
 * REAL assistant answers over the seeded demo business. Every reply costs
 * tokens, so three brakes stack: the route's per-IP throttle, a per-session
 * budget that ends in the register invite, and a daily global fuse.
 */
class DemoChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:200'],
            'rubro' => ['sometimes', 'string', 'in:'.implode(',', array_keys(Business::DEMO_EMAILS))],
        ]);

        $business = Business::demo($validated['rubro'] ?? 'clinica');
        $used = (int) $request->session()->get('demo_messages', 0);
        $sessionCap = (int) config('atendia.demo.session_cap');

        if ($business === null || $used >= $sessionCap || $this->dailyFuseBlown()) {
            return response()->json(['done' => true]);
        }

        $request->session()->put('demo_messages', $used + 1);

        $reply = rescue(fn (): string => (new AsistenteAtendia($business))->answer($validated['message'])->text, report: true);

        if ($reply === null) {
            return response()->json(['error' => true]);
        }

        // The funnel's first two steps; the third lands on registration.
        if ($used === 0) {
            DemoMetric::bump('sessions');
        }
        DemoMetric::bump('messages');

        // `left` feeds the "te quedan :count preguntas" nudge under the box.
        return response()->json([
            'reply' => $reply,
            'done' => $used + 1 >= $sessionCap,
            'left' => max(0, $sessionCap - ($used + 1)),
        ]);
    }

    /** One counter per day; `add` seeds it with its TTL before the bump. */
    private function dailyFuseBlown(): bool
    {
        $key = 'demo-chat:'.now()->toDateString();

        Cache::add($key, 0, now()->addDay());

        return Cache::increment($key) > (int) config('atendia.demo.daily_cap');
    }
}
