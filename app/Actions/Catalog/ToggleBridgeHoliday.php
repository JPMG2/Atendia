<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CountryHoliday;

class ToggleBridgeHoliday
{
    public const string CREATED = 'created';

    public const string SWITCHED_ON = 'switched_on';

    public const string SWITCHED_OFF = 'switched_off';

    /**
     * Marks a day as a one-year holiday of the country, or flips the one that
     * is already there. A switched-off day is brought back and never
     * duplicated: holidays are kept, not deleted.
     *
     * @return self::CREATED|self::SWITCHED_ON|self::SWITCHED_OFF
     */
    public function handle(int $countryId, string $date): string
    {
        $existing = CountryHoliday::onceOn($countryId, $date);

        if ($existing === null) {
            CountryHoliday::query()->create([
                'country_id' => $countryId,
                'name' => __('catalog.country_holiday.bridge.name'),
                'on_date' => $date,
                'is_active' => true,
            ]);

            return self::CREATED;
        }

        $existing->update(['is_active' => ! $existing->is_active]);

        return $existing->is_active ? self::SWITCHED_ON : self::SWITCHED_OFF;
    }
}
