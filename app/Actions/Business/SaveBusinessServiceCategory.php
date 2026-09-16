<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\ServiceCategory;

/**
 * Creates one shelf through its owner. A reused trashed name restores the
 * old shelf — the unique owner+name outlives a soft delete.
 */
class SaveBusinessServiceCategory
{
    public function handle(Business $business, string $name): ServiceCategory
    {
        $category = $business->serviceCategories()->withTrashed()->firstOrNew(['name' => $name]);

        if ($category->trashed()) {
            $category->restore();
        }

        if (! $category->exists) {
            $category->sort_order = ((int) $business->serviceCategories()->max('sort_order')) + 1;
        }

        $category->save();

        return $category;
    }
}
