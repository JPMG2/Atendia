<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ComplianceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Cumplimiento" as the web prints it, from the same model readings and in
 * the same words: the list with its search and situation, and one business's
 * history when it is opened. Read only.
 */
class AdminComplianceController extends Controller
{
    private const int PAGE = 25;

    /** @var list<string> the situations the web's filter offers */
    private const array FILTERS = [ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT, 'recent'];

    public function index(Request $request): JsonResponse
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : '';
        $search = is_string($request->query('search')) ? mb_substr(trim($request->query('search')), 0, 120) : '';
        $matching = ComplianceEvent::boardCount($filter, $search);
        // The web loads 25 more until all are shown; a page past the last one asks for no more rows than exist.
        $pages = max(1, min((int) ceil($matching / self::PAGE), is_numeric($request->query('page')) ? (int) $request->query('page') : 1));
        $rows = ComplianceEvent::board($filter, $search, self::PAGE * $pages);
        $business = $request->user()?->can('businesses.view') === true;

        return response()->json(['data' => [
            'title' => __('compliance.title'),
            'sub' => __('compliance.sub'),
            'read_at' => __('compliance.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]),
            'count' => trans_choice('compliance.count', $matching, ['count' => $matching]),
            'search' => ['label' => __('compliance.search'), 'placeholder' => __('compliance.search_placeholder'), 'value' => $search],
            'filter' => $filter,
            'clear' => __('admin.keys.clear_filters'),
            'filters' => [
                ['value' => '', 'label' => __('compliance.filters.all')],
                ...array_map(fn (string $value): array => ['value' => $value, 'label' => __('compliance.filters.'.$value)], self::FILTERS),
            ],
            // Nothing found is not the same sentence as nothing happened, as on the web.
            'empty' => $rows->isNotEmpty() ? null : ($filter === '' && $search === ''
                ? ['title' => __('compliance.empty_title'), 'body' => __('compliance.empty_body')]
                : ['title' => __('compliance.no_match'), 'body' => '']),
            'labels' => [
                'recent' => __('compliance.table.recent', ['days' => ComplianceEvent::RECENT_DAYS]),
                'total' => __('compliance.table.total'),
                'last' => __('compliance.table.last'),
                'open' => __('compliance.open'),
                'close' => __('compliance.close'),
            ],
            'rows' => $rows->map(fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'standing' => ['key' => $row['standing'], 'label' => __('compliance.standing.'.$row['standing']), 'tone' => $this->tone($row['standing'])],
                'recent' => $row['recent'],
                'total' => $row['total'],
                'last' => $row['last_kind'] === null ? '—' : $row['last_kind']->label().' · '.$row['last_at']?->format('d/m/Y'),
                // The file opens only for whoever may open Negocios: no door that ends in a 403.
                'target' => $business ? ['screen' => 'admin.businesses', 'query' => ['negocio' => $row['id']]] : null,
            ])->values()->all(),
            'has_more' => $rows->count() < $matching,
        ]]);
    }

    /** One business's history, newest first, as the web's side panel shows it. */
    public function show(int $business): JsonResponse
    {
        $found = ComplianceEvent::businessOf($business);
        abort_if($found === null, 404);

        $timeline = ComplianceEvent::timelineOf($business);
        $standing = ComplianceEvent::standingOf($found, $timeline->where('occurred_at', '>=', now()->subDays(ComplianceEvent::RECENT_DAYS))->count());

        return response()->json(['data' => [
            'id' => $found->id,
            'name' => $found->name,
            'subtitle' => trans_choice('compliance.panel.subtitle', $timeline->count(), ['count' => $timeline->count()]),
            'standing' => ['key' => $standing, 'label' => __('compliance.standing.'.$standing), 'tone' => $this->tone($standing), 'hint' => __('compliance.standing_hint.'.$standing)],
            'empty' => $timeline->isEmpty() ? __('compliance.panel.empty') : null,
            'events' => $timeline->map(fn (ComplianceEvent $event): array => [
                'id' => $event->id,
                'label' => $event->kind->label(),
                'tone' => $event->kind->tone(),
                'detail' => $event->readableDetail(),
                'at' => $event->occurred_at->format('d/m/Y H:i'),
                'by' => $event->actor === null ? null : __('compliance.by', ['name' => $event->actor->name]),
            ])->values()->all(),
        ]]);
    }

    /** The web's tag colour for a standing. */
    private function tone(string $standing): string
    {
        return match ($standing) {
            ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT => 'danger',
            ComplianceEvent::OBSERVED => 'warning',
            default => 'success',
        };
    }
}
