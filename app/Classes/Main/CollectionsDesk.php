<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SubscriptionStatus;
use App\Models\RevenueSnapshot;
use App\Models\Subscription;
use App\Traits\FormatsPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Cobranza" of the admin as data, with the words it prints: the web screen and the mobile API both
 * read from here, so a figure or a row cannot say one thing on the panel and another on the phone.
 * The queue is sorted by what is at stake, in PHP, so every call weighs the whole of it: `visible`
 * only cuts what is handed over, never what the three figures above it count.
 */
class CollectionsDesk
{
    use FormatsPrice;

    public const int PAGE = 25;

    /** @param  int|null  $visible  rows of the queue to hand over; null = all of them, as the web screen shows them */
    public function __construct(public readonly ?int $visible = null) {}

    /** @var Collection<int, Subscription> every business to ring, worth first */
    public Collection $queue {
        get => $this->queue ??= Subscription::collectionQueue();
    }

    public bool $isEmpty {
        get => $this->queue->isEmpty();
    }

    /** @var float the money hanging off subscriptions that did not pay */
    public float $atRisk {
        get => $this->atRisk ??= Subscription::amountAtRisk();
    }

    /** @var array{grace: int, paused: int} the businesses behind on a payment, by how far behind */
    public array $owing {
        get {
            $behind = $this->queue->filter(fn (Subscription $row): bool => $row->isBehind());

            return [
                'grace' => $behind->where('status', SubscriptionStatus::PastDue)->count(),
                'paused' => $behind->where('status', SubscriptionStatus::Paused)->count(),
            ];
        }
    }

    public float $revenue {
        get => $this->revenue ??= Subscription::monthlyRecurringRevenue();
    }

    /** @var RevenueSnapshot|null last month's photo; null on the first month measured, and null is not zero */
    public ?RevenueSnapshot $previous {
        get => $this->previous ??= RevenueSnapshot::before(CarbonImmutable::now());
    }

    /** @var float|null what changed since that photo, in money */
    public ?float $change {
        get => $this->previous === null ? null : $this->revenue - (float) $this->previous->mrr;
    }

    public string $readAt {
        get => __('collections.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]);
    }

    /** @var list<array{key: string, label: string, value: string, hint: string|null, icon: string, tint: string, delta: string|null, trend: string|null, target: array{screen: string, query: array<string, string>}|null}> */
    public array $cards {
        get {
            $owing = $this->owing;
            $count = $owing['grace'] + $owing['paused'];
            $change = $this->change;

            return [
                [
                    'key' => 'at_risk',
                    'label' => __('collections.risk.at_risk'),
                    'value' => 'USD '.self::formatPrice($this->atRisk),
                    'hint' => __('collections.risk.at_risk_hint'),
                    'icon' => 'alert-triangle',
                    'tint' => 'accent',
                    'delta' => null,
                    'trend' => null,
                    'target' => null,
                ],
                [
                    'key' => 'owing',
                    'label' => trans_choice('collections.risk.owing', $count),
                    'value' => (string) $count,
                    'hint' => __('collections.risk.owing_split', $owing),
                    'icon' => 'receipt',
                    'tint' => 'warning',
                    'delta' => null,
                    'trend' => null,
                    // The web's tile lands on exactly those businesses: the list already cut by "problem".
                    'target' => ['screen' => 'admin.businesses', 'query' => ['estado' => 'problem']],
                ],
                [
                    'key' => 'mrr',
                    'label' => __('collections.risk.mrr'),
                    'value' => 'USD '.self::formatPrice($this->revenue),
                    'hint' => $change === null ? __('collections.risk.no_history') : null,
                    'icon' => 'bar-chart-3',
                    'tint' => 'brand',
                    'delta' => $change === null ? null : __('collections.risk.vs_month', [
                        'amount' => ($change >= 0 ? '+' : '−').self::formatPrice(abs($change)),
                        'month' => $this->previous->month->translatedFormat('F'),
                    ]),
                    'trend' => $change === null ? null : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')),
                    'target' => null,
                ],
            ];
        }
    }

    /** @var Collection<int, Subscription> the slice of the queue handed over */
    public Collection $slice {
        get => $this->visible === null ? $this->queue : $this->queue->take($this->visible);
    }

    public bool $hasMore {
        get => $this->slice->count() < $this->queue->count();
    }

    /** @var list<array{id: int, ficha: int, business: string, plan: string, cycle: string, monthly: string, state: string, state_label: string, tone: string, since: string}> */
    public array $rows {
        get => $this->slice->map(function (Subscription $row): array {
            $days = $row->daysUntilPayment();
            $state = match ($row->status) {
                SubscriptionStatus::Paused => 'paused',
                SubscriptionStatus::PastDue => 'grace',
                default => 'renewing',
            };

            return [
                'id' => $row->id,
                // The business whose ficha this row opens.
                'ficha' => (int) $row->business_id,
                'business' => (string) $row->business?->name,
                'plan' => __('plan.names.'.$row->plan),
                'cycle' => __('collections.cycle_'.$row->billing_cycle),
                'monthly' => 'USD '.self::formatPrice($row->monthlyValue()),
                'state' => $state,
                'state_label' => __('collections.states.'.$state),
                'tone' => ['paused' => 'danger', 'grace' => 'warning', 'renewing' => 'neutral'][$state],
                // The plural phrases carry a leading space of their own: HTML ate it on the web, a phone would show it.
                'since' => match (true) {
                    $days === null => '—',
                    $days < 0 => trim(trans_choice('collections.overdue', abs($days), ['count' => abs($days)])),
                    default => trim(trans_choice('collections.due_in', $days, ['count' => $days])),
                },
            ];
        })->values()->all();
    }

    /** @var array{business: string, plan: string, monthly: string, state: string, since: string} */
    public array $labels {
        get => [
            'business' => __('collections.table.business'),
            'plan' => __('collections.table.plan'),
            'monthly' => __('collections.table.monthly'),
            'state' => __('collections.table.state'),
            'since' => __('collections.table.since'),
        ];
    }
}
