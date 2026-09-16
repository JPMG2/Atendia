<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Product;

/**
 * Flips one product between in stock and out of stock from its row. Out of
 * stock stays VISIBLE — the assistant answers "sin stock por ahora" — which
 * is why this never touches `is_active` (stop selling it altogether).
 */
class ToggleBusinessProduct
{
    public function handle(Business $business, int $id): Product
    {
        $product = $business->products()->findOrFail($id);

        $product->in_stock = ! $product->in_stock;
        $product->save();

        return $product;
    }
}
