<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Dto\ExchangeRateReadingDto;
use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use App\Services\ExchangeRates\BcvPage;
use App\Services\ExchangeRates\DolarApi;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One look at the sources, in her order: the BCV page charges; when it fails
 * or brings a value out of range, the official number from dolarapi (the same
 * figure by another road); when both fail, nothing is written and the last
 * stored rate keeps charging until she types one. The parallel never charges.
 */
class FetchExchangeRate
{
    public const string LAST_CHECK = 'exchange-rate:last-check';

    /** Shared with the manual entry: the two must not write the same value date at once. */
    public const string WRITE_LOCK = 'exchange-rate:write';

    public function __construct(
        private readonly BcvPage $bcv,
        private readonly DolarApi $dolarApi,
    ) {}

    /** @return array{at: string, bcv: string|null, dolarapi: string|null, stored: string|null} what each source did; null means it answered */
    public function handle(): array
    {
        [$bcv, $bcvError] = $this->attempt(fn (): ExchangeRateReadingDto => $this->bcv->read());
        [$api, $apiError] = $this->attempt(fn (): ExchangeRateReadingDto => $this->dolarApi->read());

        // A source that answers with an absurd value has not answered: the
        // next one gets its turn before anything waits for her.
        if ($bcv !== null && $api !== null && ! $this->withinRange($bcv) && $this->withinRange($api)) {
            $bcvError = 'BCV page: '.ExchangeRate::formatRate($bcv->official).' is out of range';
            $bcv = null;
        }

        $charging = $bcv ?? $api;
        $stored = $charging === null
            ? null
            : Cache::lock(self::WRITE_LOCK, 15)->block(10, fn (): ExchangeRate => $this->store($charging, $api?->parallel));

        $check = [
            'at' => now()->toIso8601String(),
            'bcv' => $bcvError,
            'dolarapi' => $apiError,
            'stored' => $stored?->rate_date->toDateString(),
        ];

        Cache::forever(self::LAST_CHECK, $check);

        return $check;
    }

    /**
     * @param  callable(): ExchangeRateReadingDto  $read
     * @return array{0: ExchangeRateReadingDto|null, 1: string|null}
     */
    private function attempt(callable $read): array
    {
        try {
            return [$read(), null];
        } catch (Throwable $e) {
            return [null, mb_substr($e->getMessage(), 0, 160)];
        }
    }

    private function store(ExchangeRateReadingDto $reading, ?float $parallel): ExchangeRate
    {
        $row = ExchangeRate::forDate($reading->rateDate) ?? new ExchangeRate([
            'currency' => ExchangeRate::CURRENCY,
            'rate_date' => $reading->rateDate,
        ]);

        // What she typed for that date stays: an automatic value only fills
        // in the parallel beside it.
        if ($row->exists && $row->source === ExchangeRateSource::Manual) {
            $row->parallel ??= $parallel;
            $row->save();

            return $row;
        }

        $inRange = $this->withinRange($reading);

        $row->fill([
            'official' => $reading->official,
            'source' => $reading->source,
            'parallel' => $parallel ?? $row->parallel,
        ]);

        $row->confirmed_at = $inRange ? ($row->confirmed_at ?? now()) : null;
        $row->save();

        return $row;
    }

    /**
     * Within the jump she allows. A value date she already confirmed is the
     * reference for itself, so a last-decimal correction of a jump she said
     * yes to does not ask her again. A first rate ever charges.
     */
    private function withinRange(ExchangeRateReadingDto $reading): bool
    {
        $same = ExchangeRate::forDate($reading->rateDate);
        $reference = $same?->confirmed_at !== null && $same->source !== ExchangeRateSource::Manual
            ? (float) $same->official
            : ExchangeRate::previousOfficial($reading->rateDate);

        return ExchangeRate::isWithinJump($reading->official, $reference);
    }
}
