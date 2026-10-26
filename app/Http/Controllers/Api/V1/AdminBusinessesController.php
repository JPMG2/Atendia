<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\BusinessLedger;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Negocios" of the admin as the web prints it, from the same piece and in the same words. Read only:
 * cancelling a plan, restoring or removing a business are the delivery of irreversible actions, with
 * the second step asked again, and a button for them is not drawn until it works.
 */
class AdminBusinessesController extends Controller
{
    /** @var int the most pages one call may ask for: the list grows in SQL, never loads the table */
    private const int MAX_PAGES = 20;

    public function index(Request $request): JsonResponse
    {
        $state = in_array($request->query('state'), BusinessLedger::STATES, true) ? (string) $request->query('state') : '';
        // `?search[]=x` arrives as an array: casting it would be a 500, so only a string is a search.
        $term = $request->query('search');
        $search = is_string($term) ? mb_substr(trim($term), 0, 100) : '';
        $pages = max(1, min(self::MAX_PAGES, (int) $request->query('page', 1)));
        $ledger = new BusinessLedger($search, $state, BusinessLedger::PAGE * $pages);

        return response()->json(['data' => [
            'title' => __('admin.businesses.title'),
            'sub' => __('admin.businesses.sub'),
            'count_label' => $ledger->countLabel,
            'labels' => $ledger->labels,
            'open' => __('admin.businesses.open'),
            'search' => [
                'label' => __('admin.businesses.search'),
                'placeholder' => __('admin.businesses.search_placeholder'),
                'value' => $search,
            ],
            'states' => [
                'label' => __('admin.businesses.state'),
                'all' => __('admin.businesses.all_states'),
                'value' => $state,
                'options' => $ledger->stateOptions,
            ],
            'clear' => __('admin.keys.clear_filters'),
            'empty' => $ledger->emptyText,
            'rows' => $ledger->rows,
            // At the last page the app is told there is no more: a "load more" that cannot advance must not be drawn.
            'has_more' => $ledger->hasMore && $pages < self::MAX_PAGES,
            // Instances the bridge keeps for nobody: only for whoever may manage businesses, as on the web. Seen, not freed.
            'orphans' => $request->user()->can('businesses.manage') ? $ledger->orphans : null,
        ]]);
    }

    public function show(int $business): JsonResponse
    {
        $ficha = (new BusinessLedger)->ficha($business);

        abort_if($ficha === null, 404);

        return response()->json(['data' => $ficha]);
    }
}
