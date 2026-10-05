<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\AiUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a business cost, month by month, behind the month on screen.
 *
 * A single figure says what happened; it does not say whether it is where this
 * business always sits or where it arrived last week. The whole run is read in
 * ONE query and valued by the same `AiPrices` the month uses, so the trend can
 * never tell a different story from the row it sits in.
 */
final class AiTrend
{
    /** @var Collection<int, object> Every metered row of the run, read once. */
    private Collection $rows;

    private AiPrices $prices;

    /** @var array<int, CarbonInterface> The months, oldest first. */
    private array $months;

    /** @var array<string, array<int, float>> One series per business, built on demand. */
    private array $series = [];

    private function __construct(CarbonInterface $upTo, int $months)
    {
        $this->months = collect(range($months - 1, 0))
            ->map(fn (int $back): CarbonInterface => $upTo->copy()->startOfMonth()->subMonths($back))
            ->all();

        $this->rows = AiUsage::trailingTotals($this->months[0], $upTo);
        $this->prices = new AiPrices;
    }

    /** The run of months ending on the one being read. */
    public static function upTo(CarbonInterface $month, int $months = 12): self
    {
        return new self($month, max(2, $months));
    }

    /** How many months the series carries. */
    public int $length {
        get => count($this->months);
    }

    /** Nothing was metered in the whole run: there is no shape to draw. */
    public bool $isEmpty {
        get => $this->rows->isEmpty();
    }

    /**
     * One business's cost per month, oldest first. A month it did not spend in
     * is a measured zero, not a gap: the meter was running.
     *
     * @return array<int, float>
     */
    public function seriesFor(?int $businessId): array
    {
        $key = $businessId === null ? 'platform' : (string) $businessId;

        return $this->series[$key] ??= $this->build($businessId);
    }

    /** @return array<int, float> */
    private function build(?int $businessId): array
    {
        $mine = $this->rows
            ->filter(fn (object $row): bool => $row->business_id === $businessId)
            ->groupBy('month');

        return collect($this->months)
            ->map(function (CarbonInterface $month) use ($mine): float {
                $rows = $mine->get($month->format('Y-m'), new Collection)
                    ->filter(fn (object $row): bool => $this->prices->valuable($row, $month));

                return $rows->isEmpty() ? 0.0 : $this->prices->costOf($rows, $month);
            })
            ->all();
    }

    /** @var string Where the run starts, for the label under the shape. */
    public string $span {
        get => $this->months[0]->translatedFormat('M Y');
    }
}
