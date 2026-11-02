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
 * ve.dolarapi.com: the same official BCV number by another road, plus the
 * parallel. The official one is the backup of the BCV page; the parallel is
 * only ever shown.
 */
class DolarApi
{
    public const string URL = 'https://ve.dolarapi.com/v1/dolares';

    /** @throws RuntimeException when it does not answer or the official rate is missing */
    public function read(): ExchangeRateReadingDto
    {
        $response = Http::acceptJson()->connectTimeout(10)->timeout(20)->get(self::URL);

        if (! $response->successful()) {
            throw new RuntimeException('dolarapi answered HTTP '.$response->status());
        }

        return self::parse((array) $response->json());
    }

    /**
     * @param  array<int, mixed>  $rows
     *
     * @throws RuntimeException
     */
    public static function parse(array $rows): ExchangeRateReadingDto
    {
        $byKind = collect($rows)->filter(fn (mixed $row): bool => is_array($row))->keyBy(fn (array $row): string => (string) ($row['fuente'] ?? ''));
        $official = $byKind->get('oficial');

        if ($official === null || ! is_numeric($official['promedio'] ?? null) || (float) $official['promedio'] <= 0 || empty($official['fechaActualizacion'])) {
            throw new RuntimeException('dolarapi: no official rate in the answer');
        }

        $parallel = $byKind->get('paralelo')['promedio'] ?? null;

        return new ExchangeRateReadingDto(
            ExchangeRateSource::DolarApi,
            CarbonImmutable::parse((string) $official['fechaActualizacion'])->setTimezone(ExchangeRate::TIMEZONE)->startOfDay(),
            (float) $official['promedio'],
            is_numeric($parallel) && (float) $parallel > 0 ? (float) $parallel : null,
        );
    }
}
