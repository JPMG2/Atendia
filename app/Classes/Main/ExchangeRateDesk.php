<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Billing\FetchExchangeRate;
use App\Enums\ExchangeRateSource;
use App\Models\Company;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The exchange-rate screen as data, with the words it prints: which rate
 * charges, where it came from, what each source did on the last look, and
 * the history. Writing (typing or confirming a rate) lives in the form.
 */
class ExchangeRateDesk
{
    public ?ExchangeRate $charging {
        get => $this->charging ??= ExchangeRate::charging();
    }

    public ?ExchangeRate $waiting {
        get => $this->waiting ??= ExchangeRate::awaitingConfirmation();
    }

    /** @var array{at?: string, bcv?: string|null, dolarapi?: string|null, stored?: string|null} */
    public array $lastCheck {
        get => $this->lastCheck ??= (array) Cache::get(FetchExchangeRate::LAST_CHECK, []);
    }

    public bool $isStale {
        get => $this->charging !== null && $this->charging->isStale((int) config('atendia.exchange_rate.stale_business_days'));
    }

    public bool $bcvFailed {
        get => ($this->lastCheck['at'] ?? null) !== null && ($this->lastCheck['bcv'] ?? null) !== null;
    }

    public bool $apiFailed {
        get => ($this->lastCheck['at'] ?? null) !== null && ($this->lastCheck['dolarapi'] ?? null) !== null;
    }

    /**
     * ok | fallback | sources_down | manual | stale | none. Both sources
     * failing while the stored rate is still current is a warning, not "no
     * rate"; and a rate she typed is named as hers, whatever the sources did.
     */
    public string $state {
        get => match (true) {
            $this->charging === null => 'none',
            $this->isStale => 'stale',
            $this->charging->source === ExchangeRateSource::Manual => 'manual',
            $this->bcvFailed && $this->apiFailed => 'sources_down',
            $this->bcvFailed => 'fallback',
            default => 'ok',
        };
    }

    /** "Consultada hoy 02/11 a las 09:00", or that it was never looked up. */
    public string $checkedAt {
        get {
            $at = $this->lastCheck['at'] ?? null;

            if ($at === null) {
                return __('exchange_rate.never_checked');
            }

            $local = CarbonImmutable::parse($at)->setTimezone(Company::localTimezone());

            return __('exchange_rate.checked_at', ['date' => $local->format('d/m'), 'time' => $local->format('H:i')]);
        }
    }

    /** "8:00 a 18:00": her window, as the subtitle says it. */
    public string $window {
        get => __('exchange_rate.sub', [
            'from' => (string) config('atendia.exchange_rate.first_check'),
            'to' => (string) config('atendia.exchange_rate.last_check'),
            'city' => Company::clockCity(),
        ]);
    }

    /** @var array{code: string, name: string, dollars: int}|null the plan the landing features, to see the rate in money */
    public ?array $featuredPlan {
        get {
            $plan = collect(Plan::ladder())->first(fn (Plan $tier): bool => $tier->isFeatured);

            return $plan === null ? null : ['code' => $plan->code, 'name' => __('plan.names.'.$plan->code), 'dollars' => $plan->price];
        }
    }

    /** @var Collection<int, ExchangeRate> */
    public Collection $history {
        get => $this->history ??= ExchangeRate::history((int) config('atendia.exchange_rate.history_rows'));
    }

    public const int TREND_DAYS = 30;

    /**
     * The last 30 days of each line, oldest first, and how far each moved from
     * its first point: a rate without its comparison does not say whether
     * today is a bad day. Null when there are fewer than two points to draw.
     *
     * @var array{official: array{values: list<float>, line: string}|null, parallel: array{values: list<float>, line: string}|null}
     */
    public array $trend {
        get => $this->trend ??= (function (): array {
            $rows = ExchangeRate::lastDays(self::TREND_DAYS);

            return [
                'official' => $this->trendOf($rows->map(fn (ExchangeRate $rate): float => (float) $rate->official)->values()->all()),
                'parallel' => $this->trendOf($rows->whereNotNull('parallel')->map(fn (ExchangeRate $rate): float => (float) $rate->parallel)->values()->all()),
            ];
        })();
    }

    /**
     * @param  list<float>  $values
     * @return array{values: list<float>, line: string}|null
     */
    private function trendOf(array $values): ?array
    {
        if (count($values) < 2) {
            return null;
        }

        $first = $values[0];
        $last = $values[count($values) - 1];
        $change = $first > 0 ? ($last / $first - 1) * 100 : 0.0;

        return [
            'values' => $values,
            'line' => __('exchange_rate.trend.line', [
                'days' => self::TREND_DAYS,
                'from' => ExchangeRate::formatRate($first),
                'to' => ExchangeRate::formatRate($last),
                'change' => ($change >= 0 ? '+' : '−').number_format(abs($change), 1, ',', '.'),
            ]),
        ];
    }

    /** The jump of the waiting value against the one charging, in percent. */
    public ?string $waitingJump {
        get {
            if ($this->waiting === null || $this->charging === null || (float) $this->charging->official <= 0) {
                return null;
            }

            return number_format(abs((float) $this->waiting->official / (float) $this->charging->official - 1) * 100, 1, ',', '.');
        }
    }
}
