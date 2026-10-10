<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Mail\HolidayBridgeNotice;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\CountryHoliday;
use Carbon\CarbonImmutable;

class NotifyBridgeHolidays
{
    /**
     * Mails the businesses of a country about bridge days. The dates come from
     * the browser, so each one is checked against the table: only a day that
     * really is an active one-year holiday of that country goes out.
     *
     * @param  list<string>  $dates
     * @return int How many businesses were mailed.
     */
    public function handle(int $countryId, array $dates): int
    {
        $real = collect($dates)
            // The shape is checked first: Carbon's strict mode throws on text it cannot read at all.
            ->filter(fn (mixed $date): bool => is_string($date)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
                && CarbonImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date)
            ->unique()
            ->take(MarkBridgeRange::MAX_DAYS)
            ->filter(fn (string $date): bool => CountryHoliday::onceOn($countryId, $date)?->is_active === true)
            ->sort()
            ->values()
            ->all();

        if ($real === []) {
            return 0;
        }

        $businesses = Business::reachableInCountry($countryId);

        foreach ($businesses as $business) {
            $emails = $business->users->pluck('email')->filter()->unique()->values()->all();

            (new Email($business, $emails, HolidayBridgeNotice::class, [$real]))->send();
        }

        return $businesses->count();
    }
}
