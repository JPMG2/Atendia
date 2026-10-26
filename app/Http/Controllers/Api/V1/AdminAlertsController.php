<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\PlatformBoard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Tablero de avisos" as the web prints it, from the same piece and in the
 * same words. Read only: marking an alarm as seen is the next delivery, and a
 * button for it is not drawn until it works.
 */
class AdminAlertsController extends Controller
{
    /** @var int the most pages one call may ask for: the list grows in SQL, never loads the table */
    private const int MAX_PAGES = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $filter = in_array($request->query('filter'), PlatformBoard::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $pages = max(1, min(self::MAX_PAGES, (int) $request->query('page', 1)));
        $board = new PlatformBoard($filter, PlatformBoard::PAGE * $pages);

        return response()->json(['data' => [
            'title' => __('platform_events.screen.title'),
            'sub' => __('platform_events.screen.sub'),
            'read_at' => $board->readAt,
            'poll_seconds' => PlatformBoard::POLL_SECONDS,
            'quiet' => $board->quiet,
            'verdict' => $board->verdict,
            'cards' => array_map(fn (array $card): array => [...$card, 'filter' => $card['key']], $board->cards),
            'filter' => $filter,
            'filters' => $board->filters,
            'events' => [
                'title' => __('platform_events.screen.open'),
                'sub' => __('platform_events.screen.open_sub'),
                'go' => __('platform_events.screen.go'),
                'empty' => ['title' => __('platform_events.screen.empty_title'), 'body' => __('platform_events.screen.empty_body')],
                'rows' => array_map(fn (array $row): array => [...$row, 'target' => $this->target($row['target'])], $board->rows),
                'has_more' => $board->hasMore,
            ],
            'sensors' => [
                'title' => __('platform_events.screen.sensors'),
                'sub' => __('platform_events.screen.sensors_sub'),
                'empty' => __('platform_events.screen.no_sensors'),
                'labels' => [
                    'checked' => __('platform_events.screen.detail_checked'),
                    'since' => __('platform_events.screen.detail_since'),
                    'failures' => __('platform_events.screen.detail_failures'),
                    'says' => __('platform_events.screen.detail_says'),
                    'fix' => __('platform_events.screen.detail_fix'),
                    'history' => __('platform_events.screen.history'),
                    'history_hint' => __('platform_events.screen.history_hint'),
                    'open' => __('platform_events.screen.detail_open'),
                ],
                'groups' => array_map(
                    fn (array $group): array => [...$group, 'sensors' => array_map(fn (array $sensor): array => [...$sensor, 'target' => $this->target($sensor['target'])], $group['sensors'])],
                    $board->sensorGroups,
                ),
            ],
        ]]);
    }

    /**
     * Where an alarm or a sensor leads, only when the app has that screen.
     *
     * @param  array{route: string, query: array<string, mixed>}|null  $target
     * @return array{screen: string, query: array<string, mixed>}|null
     */
    private function target(?array $target): ?array
    {
        return $target !== null && in_array($target['route'], (array) config('atendia.app.admin_screens'), true)
            ? ['screen' => $target['route'], 'query' => $target['query']]
            : null;
    }
}
