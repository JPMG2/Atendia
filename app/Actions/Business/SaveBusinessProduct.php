<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Product;

/**
 * Upserts ONE product through its owner — the editor sheet's writer. The
 * wizard keeps its name-only reconciler and the import job its own upsert.
 */
class SaveBusinessProduct
{
    /**
     * A new product reusing a trashed name restores that row instead of
     * colliding with it: the unique owner+name outlives a soft delete.
     *
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data, ?int $id = null): Product
    {
        $product = $id === null
            ? $business->products()->withTrashed()->firstOrNew(['name' => $data['name']])
            : $business->products()->findOrFail($id);

        if ($product->trashed()) {
            $product->restore();
        }

        $product->fill($data);
        $product->save();

        return $product;
    }
}
