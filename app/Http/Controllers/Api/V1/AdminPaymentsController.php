<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Classes\Main\PaymentsDesk;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Pagos" of the admin as the web prints it, from the same piece and in the same words. Read only:
 * crediting or rejecting a receipt moves money and wakes an assistant, so it waits for the delivery
 * of actions with the second step asked again, and the receipt file waits for a download endpoint.
 */
class AdminPaymentsController extends Controller
{
    /** @var int the most pages one call may ask for: the queue grows in SQL, never loads the table */
    private const int MAX_PAGES = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $pages = max(1, min(self::MAX_PAGES, (int) $request->query('page', 1)));
        $desk = new PaymentsDesk(PaymentsDesk::PAGE * $pages);

        return response()->json(['data' => [
            'title' => __('billing.admin.title'),
            'sub' => __('billing.admin.sub'),
            'labels' => $desk->labels,
            'pending' => [
                'title' => __('billing.admin.pending'),
                'empty' => __('billing.admin.empty'),
                'total' => $desk->pendingTotal,
                // At the last page the app is told there is no more: a "load more" that cannot advance must not be drawn.
                'has_more' => $desk->hasMore && $pages < self::MAX_PAGES,
                'rows' => array_map(fn (array $row): array => array_diff_key($row, ['receipt' => true]), $desk->pendingRows),
            ],
            'recent' => [
                'title' => __('billing.admin.recent'),
                'empty' => __('billing.admin.recent_empty'),
                'rows' => $desk->recentRows,
            ],
        ]]);
    }
}
