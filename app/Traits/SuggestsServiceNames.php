<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\SuggestedService;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Shared by the catalog levels that carry suggested services (sector and
 * activity): the wizard chips only need the names, keyed by `code`.
 */
trait SuggestsServiceNames
{
    /**
     * A name belongs to the products step when its catalog type is of the
     * `producto` modality; everything else (an untyped name too) is a service.
     * Keeps a pizza from being saved where /productos never looks.
     *
     * @param  bool  $goods  true = only the product names, false = only the services
     * @return list<string> suggested names; empty when the code is unknown
     */
    public static function suggestionsName(string $code, bool $goods = false): array
    {
        $suggested = static::query()->where('code', $code)->first()?->suggestedServices();

        if ($suggested === null) {
            return [];
        }

        $rows = $suggested instanceof Relation ? $suggested->get() : $suggested;

        return $rows->load('serviceType.modality')
            ->filter(fn (SuggestedService $row): bool => ($row->serviceType?->modality?->code === 'producto') === $goods)
            ->map(fn (SuggestedService $row): string => $row->nameFor(app()->getLocale()))
            ->values()
            ->all();
    }
}
