<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Mail\HolidayBridgeNotice;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\Country;
use App\Models\CountryHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class NotifyBridgeHolidays
{
    private const string SENT = 'holiday-notice:';

    /** Whether the last `handle()` sent nothing because every day had been told already. */
    public bool $repeated = false;

    /** What the panel says after `handle()`: how many were told, or that it had gone out already. */
    public function told(int $sent): string
    {
        return $this->repeated
            ? __('catalog.country_holiday.bridge.repeated')
            : trans_choice('catalog.country_holiday.bridge.notified', $sent, ['count' => $sent]);
    }

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
        $this->repeated = false;

        $real = collect($dates)
            // The shape is checked first: Carbon's strict mode throws on text it cannot read at all.
            ->filter(fn (mixed $date): bool => is_string($date)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
                && CarbonImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date)
            ->unique()
            ->take(MarkBridgeRange::MAX_DAYS)
            ->filter(fn (string $date): bool => $date >= CarbonImmutable::today()->toDateString()
                && CountryHoliday::onceOn($countryId, $date)?->is_active === true)
            ->sort()
            ->values()
            ->all();

        // A day already told today is not told again: a double tap, or the same offer accepted twice.
        $fresh = array_values(array_filter($real, fn (string $date): bool => Cache::add(self::SENT.$countryId.':'.$date, true, now()->addDay())));
        $this->repeated = $real !== [] && $fresh === [];
        $real = $fresh;

        if ($real === []) {
            return 0;
        }

        // A day a decree moved is a holiday, not a bridge: the mail says which.
        $bridge = collect($real)->every(fn (string $date): bool => CountryHoliday::onceOn($countryId, $date)?->name === __('catalog.country_holiday.bridge.name'));
        $businesses = Business::reachableInCountry($countryId);

        foreach ($businesses as $business) {
            $emails = $business->users->pluck('email')->filter()->unique()->values()->all();

            (new Email($business, $emails, HolidayBridgeNotice::class, [$real, $bridge]))->send();
        }

        return $businesses->count();
    }

    /**
     * The question the panel asks after a one-year day was saved: null when
     * there is nobody in that country to tell. Web and phone ask the same.
     *
     * @param  list<string>  $dates
     * @return array{marked: list<string>, title: string, message: string, accept: string, dismiss: string}|null
     */
    public function offer(int $countryId, array $dates): ?array
    {
        // Only what is still ahead is news; `handle()` drops the rest the same way.
        $dates = array_values(array_filter($dates, fn (string $date): bool => $date >= CarbonImmutable::today()->toDateString()));
        $reach = $dates === [] ? 0 : Business::reachableInCountry($countryId)->count();

        if ($reach === 0) {
            return null;
        }

        return [
            'marked' => array_values($dates),
            'title' => trans_choice('catalog.country_holiday.bridge.notify_title', $reach, [
                'count' => $reach,
                'country' => (string) Country::query()->whereKey($countryId)->value('name'),
            ]),
            'message' => __('catalog.country_holiday.bridge.notify_message'),
            'accept' => __('catalog.country_holiday.bridge.notify_accept'),
            'dismiss' => __('catalog.country_holiday.bridge.notify_skip'),
        ];
    }

    /**
     * The offer for a holiday just saved: only an active one-year day is news;
     * a holiday that repeats is already in every business's calendar.
     *
     * @return array{marked: list<string>, title: string, message: string, accept: string, dismiss: string}|null
     */
    public function offerFor(CountryHoliday $holiday): ?array
    {
        return $holiday->is_active && $holiday->on_date !== null
            ? $this->offer($holiday->country_id, [$holiday->on_date->toDateString()])
            : null;
    }
}
