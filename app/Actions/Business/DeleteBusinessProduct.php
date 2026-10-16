<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\ConversationQuestion;

/**
 * Deletes one product from the inventory, unless a customer has asked about
 * it: that question is the business's own record and would point at nothing.
 * Those keep it, and the way to stop selling it is the stock switch.
 */
class DeleteBusinessProduct
{
    public const string DELETED = 'deleted';

    public const string HELD = 'held';

    public const string MISSING = 'missing';

    public function handle(Business $business, int $id): string
    {
        $product = $business->products()->find($id);

        if ($product === null) {
            return self::MISSING;
        }

        if (ConversationQuestion::query()->where('product_id', $id)->exists()) {
            return self::HELD;
        }

        $product->delete();

        return self::DELETED;
    }
}
