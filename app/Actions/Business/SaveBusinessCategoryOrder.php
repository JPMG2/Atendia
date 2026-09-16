<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;

/**
 * Persists the drag order of the shelves. The order is data, not
 * presentation: the assistant offers in it and WhatsApp collections keep it.
 */
class SaveBusinessCategoryOrder
{
    /**
     * @param  list<int>  $orderedIds  Foreign ids are simply skipped: the relation never reaches them.
     */
    public function handle(Business $business, array $orderedIds): void
    {
        $categories = $business->serviceCategories()->get()->keyBy('id');

        foreach (array_values($orderedIds) as $position => $id) {
            $category = $categories->get($id);

            if ($category !== null && $category->sort_order !== $position) {
                $category->sort_order = $position;
                $category->save();
            }
        }
    }
}
