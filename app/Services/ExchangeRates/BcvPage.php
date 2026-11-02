<?php

declare(strict_types=1);

namespace App\Services\ExchangeRates;

use App\Dto\ExchangeRateReadingDto;
use App\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the dollar box of the BCV home page. The BCV has no API, so this
 * breaks the day its design changes: that is why it throws instead of
 * guessing, and the chain moves on to the next source.
 */
class BcvPage
{
    public const string URL = 'https://www.bcv.org.ve/';

    /**
     * The site serves the wrong intermediate certificate, so the right one
     * ships with the code and verification stays on.
     */
    public const string INTERMEDIATE = 'certs/sectigo-dv-r36.pem';

    /** @throws RuntimeException when the page does not answer or no longer looks the way it is read */
    public function read(): ExchangeRateReadingDto
    {
        $response = Http::withOptions(['verify' => resource_path(self::INTERMEDIATE)])
            ->withUserAgent('Mozilla/5.0 (compatible; Aliadito rate check)')
            ->connectTimeout(10)
            ->timeout(20)
            ->get(self::URL);

        if (! $response->successful()) {
            throw new RuntimeException('BCV page answered HTTP '.$response->status());
        }

        return self::parse($response->body());
    }

    /** @throws RuntimeException when the dollar box or its value date is not where it was */
    public static function parse(string $html): ExchangeRateReadingDto
    {
        if (preg_match('/id="dolar".*?<strong[^>]*>\s*([\d.,]+)\s*<\/strong>/s', $html, $rate) !== 1) {
            throw new RuntimeException('BCV page: the dollar box was not found');
        }

        if (preg_match('/Fecha\s+Valor:\s*<span[^>]*content="([^"]+)"/u', $html, $date) !== 1) {
            throw new RuntimeException('BCV page: the value date was not found');
        }

        return new ExchangeRateReadingDto(
            ExchangeRateSource::BcvPage,
            CarbonImmutable::parse($date[1])->setTimezone(ExchangeRate::TIMEZONE)->startOfDay(),
            self::number($rate[1]),
        );
    }

    /** "889,57540000" or "1.234,5600": the BCV writes the comma as the decimal mark. */
    private static function number(string $raw): float
    {
        $value = (float) str_replace(['.', ','], ['', '.'], $raw);

        if ($value <= 0) {
            throw new RuntimeException("BCV page: '{$raw}' is not a rate");
        }

        return $value;
    }
}
