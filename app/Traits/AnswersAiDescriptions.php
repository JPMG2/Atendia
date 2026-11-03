<?php

declare(strict_types=1);

namespace App\Traits;

use App\Actions\Catalog\WriteMissingDescriptions;
use App\Classes\Main\AiOptimizeLimit;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * "Optimizar con IA" of Servicios and Productos for the app: the very branch
 * the web's two lists take, so the phone reads the same three outcomes in the
 * same words. The ceiling is the one the web shares (AiOptimizeLimit).
 */
trait AnswersAiDescriptions
{
    /**
     * @param  Collection<int, Model>  $items  the whole catalog, as the web passes it
     * @param  string  $words  `client.services` or `client.products`
     */
    protected function writeMissing(Business $business, Collection $items, string $words): JsonResponse
    {
        $limit = AiOptimizeLimit::for($business);

        // Only when there is something to write: a catalog that reads fine costs nothing and needs no ceiling.
        if ($limit->reached && $items->contains(fn ($item): bool => trim((string) $item->description) === '')) {
            return response()->json(['message' => $limit->message, 'code' => 'ai_limit', 'ai' => $limit->toApi()], 429);
        }

        $written = app(WriteMissingDescriptions::class)->handle($business, $items);

        // Null is the model never answering, never "they all have one already".
        if ($written === null) {
            return response()->json(['message' => __($words.'.ai_failed'), 'code' => 'ai_failed', 'ai' => AiOptimizeLimit::for($business)->toApi()], 502);
        }

        return response()->json([
            'message' => trans_choice($words.'.ai_done', $written, ['count' => $written]),
            'data' => ['written' => $written, 'ai' => AiOptimizeLimit::for($business)->toApi()],
        ]);
    }
}
