<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Shared by the catalog levels that carry suggested services (sector and
 * activity): the wizard chips only need the names, keyed by `code`.
 */
trait SuggestsServiceNames
{
    /** @return list<string> suggested service names; empty when the code is unknown */
    public static function suggestionsName(string $code): array
    {
        return static::query()->where('code', $code)->first()
            ?->suggestedServices()->pluck('name')->all() ?? [];
    }
}
