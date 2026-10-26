<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\AdminAttention;
use App\Classes\Main\PlatformBoard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff's home: the web's own "what needs you today" card, with its words.
 * Every pending line comes, each with the screen that solves it only when the
 * app has that screen: dropping the others would say "all clear" over what the
 * web lists, and a button to nowhere is not drawn.
 */
class AdminHomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $screens = (array) config('atendia.app.admin_screens');

        $items = collect((new AdminAttention($request->user()))->items)
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'count' => $item['count'],
                'icon' => $item['icon'],
                'label' => trans_choice('ask_admin.today.'.$item['key'], $item['count'], ['count' => $item['count']]),
                'screen' => in_array($item['route'], $screens, true) ? $item['route'] : null,
            ])
            ->values();

        return response()->json(['data' => [
            'title' => __('admin.home.title'),
            'sub' => __('admin.home.sub'),
            'read_at' => __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i:s')]),
            'poll_seconds' => PlatformBoard::POLL_SECONDS,
            'attention' => [
                'title' => __('ask_admin.today.title'),
                'clear' => __('ask_admin.today.clear'),
                'items' => $items,
            ],
        ]]);
    }
}
