<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\PlatformBoard;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\PlatformEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Tablero de avisos" as the web prints it, from the same piece and in the
 * same words, and its "Marcar visto": offered and accepted only with the web's
 * key (`alerts.acknowledge`), marked by the model's own `acknowledgeById`.
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
        $screens = Menu::adminAppScreens();
        $asks = $request->user()?->can('ai.ask') === true;

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
                // The button's word, only for whoever may press it (the web's `@can`): no dead control.
                'ack' => $request->user()?->can('alerts.acknowledge') ? __('platform_events.screen.ack') : null,
                'empty' => ['title' => __('platform_events.screen.empty_title'), 'body' => __('platform_events.screen.empty_body')],
                // Each row's "Preguntar", as the web's: the word once, the question per row, only for whoever may ask.
                'ask' => $asks ? __('ask_admin.ask_button') : null,
                'rows' => array_map(fn (array $row): array => [
                    ...$row,
                    'target' => $this->target($row['target'], $screens),
                    'question' => $asks ? __('ask_admin.ask.alert', ['kind' => $row['title'], 'business' => $row['business']]) : null,
                ], $board->rows),
                'has_more' => $board->hasMore && $pages < self::MAX_PAGES,
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
                    fn (array $group): array => [...$group, 'sensors' => array_map(fn (array $sensor): array => [...$sensor, 'target' => $this->target($sensor['target'], $screens)], $group['sensors'])],
                    $board->sensorGroups,
                ),
            ],
        ]]);
    }

    /** Marks one alarm seen; a second look, or one resolved meanwhile, changes nothing, as on the web. */
    public function acknowledge(Request $request, int $event): JsonResponse
    {
        PlatformEvent::acknowledgeById($event, $request->user());

        return response()->json(['data' => ['state' => PlatformEvent::stateOf($event)]]);
    }

    /**
     * Where an alarm or a sensor leads, only when the app has that screen and
     * this person may open it: no door that ends in a 403.
     *
     * @param  array{route: string, query: array<string, mixed>}|null  $target
     * @param  list<string>  $screens
     * @return array{screen: string, query: array<string, mixed>}|null
     */
    private function target(?array $target, array $screens): ?array
    {
        return $target !== null && in_array($target['route'], $screens, true)
            ? ['screen' => $target['route'], 'query' => $target['query']]
            : null;
    }
}
