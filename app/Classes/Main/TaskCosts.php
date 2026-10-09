<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\AiUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What each task cost this month, and what the same calls would have cost on
 * another model. Usage is metered under the agent's name, which is the task's
 * key. The comparison reprices the SAME tokens: volume that happened, never
 * a forecast.
 */
final class TaskCosts
{
    /** @var Collection<string, Collection<int, object>> The month's metered rows, by task key. */
    private Collection $byTask;

    private AiPrices $prices;

    public function __construct(private readonly CarbonInterface $month)
    {
        $this->byTask = AiUsage::monthlyTotals($month)->groupBy('kind');
        $this->prices = new AiPrices;
    }

    /**
     * What the task spent: calls, the cost of what could be valued, and how
     * many calls had no price. Null when it made no calls: a task that did not
     * run is not a task that cost nothing.
     *
     * @return object{calls: int, cost: float|null, unpriced: int}|null
     */
    public function actual(string $taskKey): ?object
    {
        $rows = $this->byTask->get($taskKey);

        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        $valued = $rows->filter(fn (object $row): bool => $this->prices->valuable($row, $this->month));

        return (object) [
            'calls' => (int) $rows->sum('calls'),
            'cost' => $valued->isEmpty() ? null : $this->prices->costOf($valued, $this->month),
            'unpriced' => (int) $rows->reject(fn (object $row): bool => $this->prices->valuable($row, $this->month))->sum('calls'),
        ];
    }

    /**
     * The same month's tokens, priced as if every call had gone to `$code`.
     * Null when there is nothing to reprice or the candidate has no price.
     */
    public function with(string $taskKey, string $code): ?float
    {
        $rows = $this->byTask->get($taskKey);

        if ($rows === null || $rows->isEmpty() || $this->prices->priceOf($code, $this->month) === null) {
            return null;
        }

        return $this->prices->costOf(
            $rows->map(fn (object $row): object => (object) [...(array) $row, 'model' => $code]),
            $this->month,
        );
    }
}
