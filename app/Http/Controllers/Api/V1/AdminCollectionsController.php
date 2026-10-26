<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\CollectionsDesk;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Cobranza" of the admin as the web prints it, from the same piece and in the same words. Read
 * only: it holds no action, and the export buttons wait for a download endpoint.
 */
class AdminCollectionsController extends Controller
{
    /** @var int the most pages one call may ask for */
    private const int MAX_PAGES = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $pages = max(1, min(self::MAX_PAGES, (int) $request->query('page', 1)));
        $desk = new CollectionsDesk(CollectionsDesk::PAGE * $pages);

        return response()->json(['data' => [
            'title' => __('collections.title'),
            'sub' => __('collections.sub'),
            'read_at' => $desk->readAt,
            'labels' => $desk->labels,
            'open' => __('collections.open_business'),
            'empty' => ['title' => __('collections.empty_title'), 'body' => __('collections.empty_body')],
            // With nobody to ring the web draws no figures at all, only the empty state: the same here.
            'cards' => $desk->isEmpty ? [] : $desk->cards,
            // At the last page the app is told there is no more: a "load more" that cannot advance must not be drawn.
            'has_more' => $desk->hasMore && $pages < self::MAX_PAGES,
            'rows' => $desk->rows,
        ]]);
    }
}
