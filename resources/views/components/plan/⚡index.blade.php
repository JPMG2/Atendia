<?php

use App\Classes\Main\Plan;
use App\Classes\Main\PlanUsage;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Business;
use App\Models\Subscription;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Mi plan" — the current entitlements, this month's usage meters, and the
 * full ladder with locked rungs in plain sight: a padlock sells better
 * than a hidden feature (the Vercel/Notion upsell pattern).
 */
new class extends Component
{
    use HasNotifications;

    /** Mockup stage: the change lives in the screen until billing is wired. */
    public ?string $scheduledPlan = null;

    public ?string $upgradedPlan = null;

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function plan(): Plan
    {
        // An upgrade applies the moment it is asked for; the receipt confirms it later.
        if ($this->upgradedPlan !== null) {
            return Plan::named($this->upgradedPlan);
        }

        return $this->business?->plan() ?? Plan::named(null);
    }

    /** @return list<Plan> */
    #[Computed]
    public function ladder(): array
    {
        // What is for sale, plus the plan this business already holds: one on an
        // archived plan still sees where it stands.
        return Plan::ladderFor($this->plan);
    }

    /** This month's meters and the Ask quota: the same piece the home strip and the mobile app read. */
    #[Computed]
    public function usage(): ?PlanUsage
    {
        return $this->business === null ? null : new PlanUsage($this->business, $this->plan);
    }

    #[Computed]
    public function trialDaysLeft(): ?int
    {
        return $this->business?->subscription?->trialDaysLeft();
    }

    #[Computed]
    public function trialEnded(): bool
    {
        return $this->business?->subscription?->trialEnded() ?? false;
    }

    /**
     * One row per dial. "Locked" compares against the CURRENT plan, so the
     * padlock marks exactly what an upgrade would unlock.
     *
     * @return list<array{label: string, locked: bool}>
     */
    public function featuresOf(Plan $tier): array
    {
        $mine = collect($this->plan->features)->pluck('value', 'key');

        return array_map(fn (array $line): array => [
            'label' => $line['label'],
            'locked' => $line['value'] > ($mine[$line['key']] ?? 0),
        ], $tier->features);
    }

    #[Computed]
    public function subscription(): ?Subscription
    {
        return $this->business?->subscription;
    }

    #[Computed]
    public function periodEnd(): string
    {
        return $this->subscription?->periodEndsAt()?->setTimezone($this->business->localTimezone())->format('d/m/Y') ?? '';
    }

    /**
     * The button a card offers and the dialog it opens: what changes, from
     * when and what is paid today. Null on the current plan.
     *
     * @return array{label: string, variant: string, title: string, message: string, accept: string, type: string}|null
     */
    public function changeFor(Plan $tier): ?array
    {
        $current = $this->plan;

        if ($tier->code === $current->code || $this->subscription === null) {
            return null;
        }

        $name = __('plan.names.'.$tier->code);
        $date = $this->periodEnd;

        if ($this->subscription->onTrial()) {
            return [
                'label' => __('plan.change.choose', ['plan' => $name]),
                'variant' => $current->isBelow($tier) ? 'primary' : 'secondary',
                'title' => __('plan.change.trial_title', ['plan' => $name]),
                'message' => __('plan.change.trial_body', ['currency' => config('atendia.billing.currency'), 'plan' => $name, 'date' => $date, 'price' => $tier->price]),
                'accept' => __('plan.change.choose', ['plan' => $name]),
                'type' => 'info',
            ];
        }

        if ($current->isBelow($tier)) {
            return [
                'label' => __('plan.change.up', ['plan' => $name]),
                'variant' => 'primary',
                'title' => __('plan.change.up_title', ['plan' => $name]),
                'message' => __('plan.change.up_body', [
                    'currency' => config('atendia.billing.currency'),
                    'plan' => $name,
                    'amount' => number_format($this->subscription->upgradeCharge($tier), 2, ',', '.'),
                    'days' => max(0, (int) $this->subscription->daysUntilPayment()),
                    'date' => $date,
                    'price' => $this->subscription->billing_cycle === 'yearly' ? $tier->yearlyPrice : $tier->price,
                ]),
                'accept' => __('plan.change.up', ['plan' => $name]),
                'type' => 'info',
            ];
        }

        return [
            'label' => __('plan.change.down', ['plan' => $name]),
            'variant' => 'secondary',
            'title' => __('plan.change.down_title', ['plan' => $name]),
            'message' => __('plan.change.down_body', ['currency' => config('atendia.billing.currency'), 'plan' => $name, 'current' => __('plan.names.'.$current->code), 'date' => $date, 'price' => $tier->price]),
            'accept' => __('plan.change.down_accept'),
            'type' => 'warning',
        ];
    }

    /** Up applies today (receipt pending); down and any trial choice wait for the period end. */
    public function changePlan(string $code): void
    {
        $tier = collect($this->ladder)->firstWhere('code', $code);

        if ($tier === null || $this->changeFor($tier) === null) {
            return;
        }

        if (! $this->subscription->onTrial() && $this->plan->isBelow($tier)) {
            $this->upgradedPlan = $code;
            $this->scheduledPlan = null;
            // The screen reads the plan it just moved to, not the one cached earlier in this request.
            unset($this->plan);

            return;
        }

        $this->scheduledPlan = $code;
    }

    public function cancelScheduledChange(): void
    {
        $this->scheduledPlan = null;
        $this->dispatchNotification(new NotificationDto(__('plan.change.cancelled', ['plan' => __('plan.names.'.$this->plan->code)]), NotificationType::Success));
    }
};
?>

<div>
    <x-ui.page-head :title="__('plan.title')" :sub="__('plan.sub')">
        @if ($this->trialDaysLeft !== null)
            <x-ui.badge variant="brand" :dot="true">
                {{ __('plan.trial_badge', ['days' => $this->trialDaysLeft]) }}
            </x-ui.badge>
        @elseif ($this->trialEnded)
            <x-ui.badge variant="accent" :dot="true" data-testid="trial-over">
                {{ __('plan.trial_over', ['plan' => __('plan.names.'.$this->plan->code)]) }}
            </x-ui.badge>
        @endif
    </x-ui.page-head>

    {{-- The card's name and price come from the piece too, so the app cannot print them another way. --}}
    @php($card = $this->usage?->card)
    <x-ui.card class="p-6">
        <div class="plan-tier-head">
            <div>
                <p class="eyebrow">{{ __('plan.current') }}</p>
                <h2 class="plan-tier-name">{{ $card['name'] ?? __('plan.names.'.$this->plan->code) }}</h2>
            </div>
            <p class="plan-tier-price">{{ $card['price'] ?? config('atendia.billing.currency_symbol').$this->plan->price }}<small>{{ $card['per_month'] ?? __('plan.per_month') }}</small></p>
        </div>

        {{-- The list, its order and its words come from the piece the mobile app reads too. --}}
        @foreach ($this->usage?->meters ?? [] as $meter)
            @if ($meter['percent'] !== null)
                <x-ui.usage-meter :label="$meter['label']" :text="$meter['text']" :percent="$meter['percent']" :state="$meter['state']" />
            @else
                <div class="plan-meter">
                    <div class="plan-meter-head">
                        <span>{{ $meter['label'] }}</span>
                        <b>{{ $meter['text'] }}</b>
                    </div>
                </div>
            @endif
        @endforeach
    </x-ui.card>

    {{-- The owner's AI is the star of the plan: its quota gets its own card, not one more bar. --}}
    @php($ask = $this->usage?->ask)
    @if ($ask !== null)
        <div class="mt-4">
            <x-plan.ask-quota
                :used="$ask['used']"
                :cap="$ask['cap']"
                :renewsOn="$this->business->quotaRenewsOn()->format('d/m/Y')"
            />
        </div>
    @endif

    @if ($scheduledPlan !== null)
        <div class="plan-change-banner mt-4">
            <x-icon name="calendar" :size="18" />
            <p>
                {{ __('plan.change.scheduled', ['plan' => __('plan.names.'.$scheduledPlan), 'date' => $this->periodEnd]) }}
            </p>
            <x-ui.button variant="danger" size="sm" wire:click="cancelScheduledChange">
                {{ __('plan.change.cancel') }}</x-ui.button>
        </div>
    @endif

    @if ($upgradedPlan !== null)
        @php($upgradeTo = Plan::named($upgradedPlan))
        <div class="plan-change-banner is-up mt-4">
            <x-icon name="sparkles" :size="18" />
            <p>
                {{ __('plan.change.upgraded', ['currency' => config('atendia.billing.currency'), 'plan' => __('plan.names.'.$upgradedPlan), 'amount' => number_format($this->subscription->upgradeCharge($upgradeTo), 2, ',', '.')]) }}
            </p>
            <x-ui.button variant="primary" size="sm" :href="route('my-payments')" wire:navigate>
                {{ __('plan.change.upload') }}</x-ui.button>
        </div>
    @endif

    <div>
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-head-title text-xl">{{ __('plan.ladder_title') }}</h2>
        </div>
        <div class="plan-tiers">
            @foreach ($this->ladder as $tier)
                <x-ui.card class="plan-tier p-6">
                    <div class="plan-tier-head">
                        <h3 class="plan-tier-name">{{ __('plan.names.'.$tier->code) }}</h3>
                        @if ($tier->code === $scheduledPlan)
                            <x-ui.badge variant="brand" :dot="true">
                                {{ __('plan.change.scheduled_badge', ['date' => $this->periodEnd]) }}</x-ui.badge>
                        @elseif ($tier->code === $this->plan->code)
                            <x-ui.badge variant="brand">{{ __('plan.yours') }}</x-ui.badge>
                        @elseif ($tier->isFeatured)
                            <x-ui.badge variant="accent">{{ __('plan.popular') }}</x-ui.badge>
                        @endif
                    </div>
                    <p class="plan-tier-price">
                        <span>{{ config('atendia.billing.currency_symbol') }}{{ $tier->price }}<small>{{ __('plan.per_month') }}</small></span>
                    </p>

                    <ul class="mt-3">
                        @foreach ($this->featuresOf($tier) as $feature)
                            <li @class(['plan-feature', 'is-locked' => $feature['locked']])>
                                <x-icon :name="$feature['locked'] ? 'lock' : 'check'" :size="16" />
                                <span>{{ $feature['label'] }}</span>
                                @if ($feature['locked'])
                                    <span class="plan-locked-hint">
                                        {{ __('plan.locked_in', ['plan' => __('plan.names.'.$tier->code)]) }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @php($change = $this->changeFor($tier))
                    @if ($change !== null && $tier->code !== $scheduledPlan)
                        <div class="plan-tier-action">
                            <x-ui.button
                                :variant="$change['variant']"
                                size="sm"
                                :fullWidth="true"
                                data-testid="change-{{ $tier->code }}"
                                x-on:click="dialog.confirm({
                                    title: {{ \Illuminate\Support\Js::from($change['title']) }},
                                    message: {{ \Illuminate\Support\Js::from($change['message']) }},
                                    accept: {{ \Illuminate\Support\Js::from($change['accept']) }},
                                    type: '{{ $change['type'] }}',
                                }).then((ok) => ok && $wire.changePlan('{{ $tier->code }}'))"
                            >
                                {{ $change['label'] }}
                            </x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    </div>
</div>
