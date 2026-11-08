<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SalesChatMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Counts what happens in the landing's sales chat. Nothing the visitor types
 * reaches here: the quick answers are rendered in the page and this only
 * bumps a daily counter. An open counts once per session, or reopening the
 * panel would inflate the funnel.
 */
class SalesChatEventController extends Controller
{
    private const array EVENTS = ['open' => 'opens', 'answer' => 'answers', 'handoff' => 'handoffs'];

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'in:'.implode(',', array_keys(self::EVENTS))],
        ]);

        if ($validated['event'] === 'open') {
            if ($request->session()->get('sales_chat_opened', false)) {
                return response()->noContent();
            }

            $request->session()->put('sales_chat_opened', true);
        }

        SalesChatMetric::bump(self::EVENTS[$validated['event']]);

        return response()->noContent();
    }
}
