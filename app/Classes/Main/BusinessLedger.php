<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Admin\FindOrphanInstances;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * "Negocios" of the admin as data, with the words it prints: the web screen and
 * the mobile API both read from here, so a row or a ficha cannot say one thing
 * on the panel and another on the phone. What it does with a business (cancel,
 * restore, remove) is not here: those are actions, and live where they are taken.
 */
class BusinessLedger
{
    public const int PAGE = 25;

    /** @var list<string> what the list can be cut by; "problem" is the pair the Inicio shows together */
    public const array STATES = ['problem', 'trialing', 'active', 'past_due', 'paused', 'canceling', 'ended', 'suspended', 'none', 'removed'];

    /** The situations painted as bad news. */
    private const array BAD = ['suspended', 'paused', 'ended', 'removed'];

    public function __construct(
        public readonly string $search = '',
        public readonly string $state = '',
        public readonly int $visible = self::PAGE,
        public readonly ?int $open = null,
    ) {}

    /** @var int the businesses the platform holds, the "of" of "4 of 34" */
    public int $total {
        get => $this->total ??= Business::directoryTotal($this->state);
    }

    /** @var int how many match the search and the state, of which the page shows `visible` */
    public int $matching {
        get => $this->matching ??= Business::directoryMatching($this->search, $this->state);
    }

    /** @var Collection<int, Business> */
    public Collection $businesses {
        get => $this->businesses ??= Business::directory($this->search, $this->state, $this->visible);
    }

    /** @var list<array{value: string, label: string}> */
    public array $stateOptions {
        get => array_map(fn (string $state): array => ['value' => $state, 'label' => __('admin.businesses.states.'.$state)], self::STATES);
    }

    /** @var string "4 de 34 negocios" only when something is held back; otherwise the plain count, as the web's counter says it */
    public string $countLabel {
        get => $this->matching < $this->total
            ? trans_choice('admin.businesses.count_of', $this->total, ['shown' => $this->matching, 'total' => $this->total])
            : trans_choice('admin.businesses.count', $this->matching, ['count' => $this->matching]);
    }

    /** @var string nothing found is not the same sentence as nothing exists */
    public string $emptyText {
        get => $this->search === '' && $this->state === '' ? __('admin.businesses.empty') : __('admin.businesses.no_match');
    }

    /** @var list<array<string, mixed>> the page of the list, ready to print */
    public array $rows {
        get => $this->businesses->map(fn (Business $business): array => $this->rowOf($business))->values()->all();
    }

    /** @var bool whether there are businesses past this page */
    public bool $hasMore {
        get => $this->businesses->count() < $this->matching;
    }

    /** @var array<string, string> the headers of the list, in the web's words */
    public array $labels {
        get => [
            'name' => __('admin.businesses.name'),
            'plan' => __('admin.businesses.plan'),
            'state' => __('admin.businesses.state'),
            'due' => __('admin.businesses.due'),
            'owes' => __('admin.businesses.owes'),
        ];
    }

    /**
     * The bridge instances nobody holds, as the last sweep found them: the screen never calls the bridge on a render.
     *
     * @var array{title: string, sub: string, as_of: string, names: list<string>}|null
     */
    public ?array $orphans {
        get {
            $seen = FindOrphanInstances::lastKnown();

            return $seen === null || $seen['names'] === [] ? null : [
                'title' => __('admin.businesses.orphans.title'),
                'sub' => __('admin.businesses.orphans.sub'),
                'as_of' => __('admin.businesses.orphans.as_of', ['date' => $seen['at']->format('d/m H:i')]),
                'names' => $seen['names'],
            ];
        }
    }

    /**
     * The open business with its money history: the three cards, the plan row's own situation and the last payments.
     *
     * @return array<string, mixed>|null
     */
    public function ficha(int $id): ?array
    {
        $business = Business::ledger($id);

        return $business === null ? null : $this->fichaOf($business);
    }

    /**
     * @return array<string, mixed>
     */
    public function rowOf(Business $business): array
    {
        $state = $business->situation();
        $subscription = $business->subscription;

        return [
            'id' => $business->id,
            'name' => $business->name,
            'plan' => $subscription ? __('plan.names.'.$subscription->plan) : '—',
            'state' => $state,
            'state_label' => __('admin.businesses.states.'.$state),
            'bad' => in_array($state, self::BAD, true),
            'due' => $subscription?->periodEndsAt()?->format('d/m/Y') ?? '—',
            // Zero is not the same sentence as nothing owed: a dash says there is nothing to collect, not that it measured 0.
            'owes' => $business->owes() > 0 ? 'USD '.number_format($business->owes(), 2, ',', '.') : '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fichaOf(Business $business): array
    {
        $subscription = $business->subscription;
        $owes = $business->owes();

        return [
            'id' => $business->id,
            'name' => $business->name,
            'removed' => $business->trashed(),
            'cards' => [
                [
                    'key' => 'plan',
                    'label' => __('admin.businesses.card.plan'),
                    'value' => $subscription ? __('plan.names.'.$subscription->plan) : '—',
                    'sub' => $subscription
                        ? ($subscription->billing_cycle === 'yearly' ? __('admin.businesses.card.yearly') : __('admin.businesses.card.monthly'))
                        : __('admin.businesses.states.none'),
                    'icon' => 'gem',
                    'tint' => 'brand',
                ],
                [
                    'key' => 'next',
                    'label' => __('admin.businesses.card.next_amount'),
                    'value' => $subscription ? 'USD '.number_format($subscription->nextAmount(), 2, ',', '.') : '—',
                    'sub' => __('admin.businesses.card.next_date').': '.($subscription?->periodEndsAt()?->format('d/m/Y') ?? '—'),
                    'icon' => 'receipt',
                    'tint' => 'info',
                ],
                [
                    'key' => 'owes',
                    'label' => __('admin.businesses.owes'),
                    'value' => $owes > 0 ? 'USD '.number_format($owes, 2, ',', '.') : '—',
                    'sub' => __('admin.businesses.states.'.$business->situation()),
                    'icon' => 'alert-triangle',
                    'tint' => $owes > 0 ? 'warning' : 'brand',
                ],
            ],
            // What the plan row says about leaving, as a fact. The buttons that act on it are a later delivery.
            'note' => $this->noteOf($business),
            'payments' => [
                'title' => __('admin.businesses.card.payments'),
                'empty' => __('admin.businesses.card.payments_empty'),
                'labels' => [
                    'date' => __('admin.businesses.card.date'),
                    'concept' => __('admin.businesses.card.concept'),
                    'amount' => __('admin.businesses.card.amount'),
                    'status' => __('admin.businesses.card.status'),
                ],
                'rows' => $business->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'date' => $payment->created_at?->format('d/m/Y'),
                    'concept' => __('plan.names.'.$payment->plan),
                    'amount' => $payment->currency.' '.number_format((float) $payment->amount, 2, ',', '.'),
                    'status' => __('billing.history.statuses.'.$payment->status->value),
                    'rejected' => $payment->status === PaymentStatus::Rejected,
                ])->values()->all(),
            ],
        ];
    }

    /** The facts of the plan row's way out, in the web's sentences; nothing for a removed business or one on its normal course. */
    private function noteOf(Business $business): ?string
    {
        $subscription = $business->subscription;

        return match (true) {
            $business->trashed() => null,
            $subscription === null => __('admin.businesses.cancel.no_subscription'),
            $subscription->hasEnded() => __('admin.businesses.cancel.ended_on', ['date' => $subscription->periodEndsAt()?->format('d/m/Y')]),
            $subscription->isCanceling() => trim(__('admin.businesses.cancel.asked_on', ['date' => $subscription->canceled_at?->format('d/m/Y')]).' '.__('admin.businesses.cancel.ends_on', ['date' => $subscription->periodEndsAt()?->format('d/m/Y')])),
            default => null,
        };
    }
}
