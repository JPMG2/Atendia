<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * The owner's skills take explicit dates: the agent turns "hoy", "ayer" or
 * "la semana pasada" into Y-m-d from the clock in its instructions, so a
 * word never reaches a query and a period is always the business's own day.
 */
trait ReadsDateRange
{
    private const int MAX_RANGE_DAYS = 400;

    /** @return array{from: mixed, to: mixed} */
    private function dateRangeSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Primer día del período, formato AAAA-MM-DD.')->required(),
            'to' => $schema->string()->description('Último día del período (incluido), formato AAAA-MM-DD.')->required(),
        ];
    }

    /**
     * Local day bounds converted to UTC for the queries, or the reason the
     * dates cannot be read — said to the model so it retries, never guessed.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|string
     */
    private function dateRange(Request $request, Business $business): array|string
    {
        $timezone = $business->localTimezone();

        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request['from'], $timezone);
            $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request['to'], $timezone);
        } catch (Throwable) {
            return 'Fechas inválidas: usá el formato AAAA-MM-DD en from y to.';
        }

        if ($from === null || $to === null) {
            return 'Fechas inválidas: usá el formato AAAA-MM-DD en from y to.';
        }

        [$from, $to] = $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to, $from];

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return 'El período es demasiado largo: pedí como máximo '.self::MAX_RANGE_DAYS.' días.';
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->isSameDay($to)
            ? 'El '.$from->format('d/m/Y')
            : 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y');
    }
}
