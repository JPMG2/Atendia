<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\CatalogHub;
use App\Classes\Main\CatalogTable;
use App\Dto\CatalogColumnDto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Catálogos" of the admin as the web prints it, from the same models and the same words. Read only:
 * creating, editing and removing a row are the delivery of actions, and a button for them is not drawn
 * until it works. The lock is the web's: the hub's permission on the route, and each master's own after it.
 */
class AdminCatalogsController extends Controller
{
    /** @var int the most pages one call may ask for, as the other admin lists */
    private const int MAX_PAGES = 20;

    public function index(Request $request): JsonResponse
    {
        $hub = new CatalogHub($request->user());

        return response()->json(['data' => [
            'title' => __('catalog.hub.rail_label'),
            'search' => [
                'label' => __('catalog.hub.search_label'),
                'placeholder' => __('catalog.hub.search_placeholder'),
            ],
            'none' => __('catalog.hub.none'),
            'no_matches' => __('catalog.hub.no_matches'),
            'groups' => $hub->groups,
        ]]);
    }

    public function show(Request $request, string $master): JsonResponse
    {
        // `?search[]=x` arrives as an array: casting it would be a 500, so only a string is a search.
        $term = $request->query('search');
        $search = is_string($term) ? mb_substr(trim($term), 0, 100) : '';
        $pages = max(1, min(self::MAX_PAGES, (int) $request->query('page', 1)));
        $table = new CatalogTable($master, $request->user(), $search, CatalogTable::PAGE * $pages);
        $form = $table->form;

        abort_if($form === null, 404);

        return response()->json(['data' => [
            'slug' => $master,
            'title' => $form->title,
            'description' => $form->description,
            'icon' => $form->icon ?? 'library',
            'search' => [
                'label' => $table->searchLabel,
                'placeholder' => $table->searchPlaceholder,
                'value' => $search,
            ],
            'count' => $table->total,
            'unit' => $table->unit,
            'columns' => array_map(fn (CatalogColumnDto $column): array => $column->toArray(), $table->columns),
            'rows' => $table->rows,
            'empty' => $table->empty,
            // At the last page the app is told there is no more: a "load more" that cannot advance must not be drawn.
            'has_more' => $table->hasMore && $pages < self::MAX_PAGES,
        ]]);
    }
}
