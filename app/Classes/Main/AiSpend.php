<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\AiUsage;
use App\Models\Business;
use App\Models\ConversationMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a month of AI cost, per business and per kind.
 *
 * The console report and the admin screen read it from here: two readings of
 * the same month that disagree are worse than not measuring at all.
 */
final class AiSpend
{
    /** @var Collection<int, object> The month's metered rows, read once. */
    private Collection $rows;

    /** Every published price, read once, and the rules that apply them. */
    private AiPrices $prices;

    /** @var Collection<int, object>|null The board, built at most once per read. */
    private ?Collection $businesses = null;

    private function __construct(public readonly CarbonInterface $month)
    {
        $this->rows = AiUsage::monthlyTotals($month);
        $this->prices = new AiPrices;
    }

    public static function of(CarbonInterface $month): self
    {
        return new self($month->copy()->startOfMonth());
    }

    /** Nothing was metered that month. */
    public bool $isEmpty {
        get => $this->rows->isEmpty();
    }

    /**
     * Who spent, the platform's own calls (null) included.
     *
     * @var Collection<int, int|null>
     */
    public Collection $businessIds {
        get => $this->rows->pluck('business_id')->unique()->values();
    }

    /**
     * The same totals, cut by kind of call instead of by business.
     *
     * @var Collection<int, object{kind: string, calls: int, input: int, cached: int, output: int, cost: float|null}>
     */
    public Collection $byKind {
        get => $this->rows
            ->groupBy('kind')
            ->map(fn (Collection $rows, string $kind): object => $this->totalsOf($rows, 0, $kind))
            ->sortKeys()
            ->values();
    }

    /**
     * The whole month, under the same rule as every row: what could be valued,
     * and how many calls were left out of that figure.
     *
     * @var object{kind: string, calls: int, input: int, cached: int, output: int, cost: float|null, unpriced: int}
     */
    public object $bill {
        get => $this->totalsOf($this->rows, 0);
    }

    /** Whether this row can be put a price on at all. */
    private function valuable(object $row): bool
    {
        return $this->prices->valuable($row, $this->month);
    }

    /**
     * One row per business that spent or talked, dearest first. The screen and
     * the exported file read THIS, so the file can never say something else.
     * Memoized: a hook runs on every read and this one goes to the database.
     *
     * @var Collection<int, object{id: int|null, name: string, threads: int, messages: int, audio: float, totals: object, perThread: float|null, share: float|null, saved: float, alertShare: float, isOverAlert: bool}>
     */
    public Collection $board {
        get => $this->businesses ??= $this->buildBoard();
    }

    /**
     * One business's month. Audio is billed by the minute, so its seconds come
     * from the conversation meter, not from the token rows.
     *
     * @return object{kind: string, calls: int, input: int, cached: int, output: int, cost: float|null}
     */
    public function forBusiness(?int $id, int $audioSeconds = 0): object
    {
        return $this->totalsOf(
            $this->rows->filter(fn (object $row): bool => $row->business_id === $id),
            $audioSeconds,
        );
    }

    /**
     * The volume meter and the plan live apart from the token rows: a month is
     * only readable with the three together — what it cost, how many talks it
     * bought, and what the business pays for them.
     *
     * @return Collection<int, object>
     */
    private function buildBoard(): Collection
    {
        $volume = ConversationMessage::monthlyVolume($this->month)->keyBy('business_id');
        $ids = $this->businessIds->merge($volume->keys())->unique()->values();
        $businesses = Business::withPlansFor($ids);

        return $ids
            ->map(function (?int $id) use ($volume, $businesses): object {
                $traffic = $volume->get($id);
                $threads = (int) ($traffic->threads ?? 0);
                $seconds = (int) ($traffic->audio_seconds ?? 0);
                $totals = $this->forBusiness($id, $seconds);
                $business = $id === null ? null : $businesses->get($id);
                $plan = $business?->plan();
                $price = $plan?->price;
                $share = $price > 0 && $totals->cost !== null ? $totals->cost / $price : null;
                // The threshold rides the PLAN: what is cheap on a 149 USD
                // plan is ruinous on a 29 one. With no plan there is nothing
                // to be a share of, so nothing can be over it either.
                $limit = $plan?->aiAlertShare ?? 0.0;

                return (object) [
                    'id' => $id,
                    'name' => $business?->name ?? ($id === null ? __('admin.ai_usage.platform') : "#{$id}"),
                    'threads' => $threads,
                    'messages' => (int) ($traffic->messages ?? 0),
                    'audio' => $seconds / 60,
                    'totals' => $totals,
                    // What one conversation cost: the only figure that compares
                    // a busy business with a quiet one.
                    'perThread' => $threads > 0 && $totals->cost !== null ? $totals->cost / $threads : null,
                    // And what share of its plan that eats. No plan, no share:
                    // a trial that pays nothing cannot be a percentage.
                    'share' => $share,
                    'saved' => $this->savedBy($id),
                    'alertShare' => $limit,
                    'isOverAlert' => $share !== null && $limit > 0 && $share >= $limit,
                ];
            })
            ->sortByDesc(fn (object $row): float => (float) ($row->totals->cost ?? 0) * 1000 + $row->totals->calls)
            ->values();
    }

    /**
     * One figure, under the rule the whole screen obeys: the cost is what COULD
     * be valued, and `unpriced` says how many calls stayed out of it. Nothing
     * valuable at all is not zero — it is no figure.
     *
     * @param  Collection<int, object>  $rows
     * @return object{kind: string, calls: int, input: int, cached: int, output: int, cost: float|null, unpriced: int}
     */
    private function totalsOf(Collection $rows, int $audioSeconds, string $kind = ''): object
    {
        $valued = $rows->filter(fn (object $row): bool => $this->valuable($row));

        return (object) [
            'kind' => $kind,
            'calls' => (int) $rows->sum('calls'),
            'input' => (int) $rows->sum('input_tokens'),
            'cached' => (int) $rows->sum('cached_tokens'),
            'output' => (int) $rows->sum('output_tokens'),
            'cost' => $valued->isEmpty() ? null : $this->prices->costOf($valued, $this->month, $audioSeconds),
            'unpriced' => (int) $rows->reject(fn (object $row): bool => $this->valuable($row))->sum('calls'),
        ];
    }

    /**
     * What the cache took off this month's bill, as money. A share on its own
     * says nothing about whether it is worth defending.
     */
    public float $saved {
        get => $this->prices->savingOf($this->rows, $this->month);
    }

    /** What the cache took off one business's bill. */
    public function savedBy(?int $id): float
    {
        return $this->prices->savingOf(
            $this->rows->filter(fn (object $row): bool => $row->business_id === $id),
            $this->month,
        );
    }
}
