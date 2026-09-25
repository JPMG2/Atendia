<?php

use App\Classes\Main\Plan;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Business;
use App\Models\Subscription;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
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
        return Plan::ladder();
    }

    #[Computed]
    public function conversationsUsed(): int
    {
        return $this->business?->conversationsThisMonth() ?? 0;
    }

    #[Computed]
    public function audioMinutesUsed(): int
    {
        return (int) ceil(($this->business?->audioSecondsThisMonth() ?? 0) / 60);
    }

    #[Computed]
    public function askQuestionsUsed(): int
    {
        return $this->business?->askQuestionsThisMonth() ?? 0;
    }

    #[Computed]
    public function trialDaysLeft(): ?int
    {
        return $this->business?->subscription?->trialDaysLeft();
    }

    /** Meter color, shared with the home strip via the Plan class. */
    public function meterState(int $used, int $cap): string
    {
        return Plan::usageState($used, $cap);
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
                'message' => __('plan.change.trial_body', ['plan' => $name, 'date' => $date, 'price' => $tier->price]),
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
            'message' => __('plan.change.down_body', ['plan' => $name, 'current' => __('plan.names.'.$current->code), 'date' => $date, 'price' => $tier->price]),
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

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('plan.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('plan.title') }}</h1>
            <p class="page-head-sub">{{ __('plan.sub') }}</p>
        </div>
        @if ($this->trialDaysLeft !== null)
            <x-ui.badge variant="brand" :dot="true">
                {{ __('plan.trial_badge', ['days' => $this->trialDaysLeft]) }}
            </x-ui.badge>
        @endif
    </div>

    <x-ui.card class="p-6">
        <div class="plan-tier-head">
            <div>
                <p class="eyebrow">{{ __('plan.current') }}</p>
                <h2 class="plan-tier-name">{{ __('plan.names.'.$this->plan->code) }}</h2>
            </div>
            <p class="plan-tier-price">${{ $this->plan->price }}<small>{{ __('plan.per_month') }}</small></p>
        </div>

        <x-ui.usage-meter
            :label="__('plan.meters.conversations')"
            :text="__('plan.meters.conversations_of', ['used' => number_format($this->conversationsUsed, 0, ',', '.'), 'cap' => number_format($this->plan->conversationsPerMonth, 0, ',', '.')])"
            :percent="(int) round($this->conversationsUsed / max(1, $this->plan->conversationsPerMonth) * 100)"
            :state="$this->meterState($this->conversationsUsed, $this->plan->conversationsPerMonth)"
        />

        @if ($this->plan->allowsAudio)
            <x-ui.usage-meter
                :label="__('plan.meters.audio')"
                :text="__('plan.meters.audio_of', ['used' => $this->audioMinutesUsed, 'cap' => $this->plan->audioMinutesPerMonth])"
                :percent="(int) round($this->audioMinutesUsed / max(1, $this->plan->audioMinutesPerMonth) * 100)"
                :state="$this->meterState($this->audioMinutesUsed, $this->plan->audioMinutesPerMonth)"
            />
        @endif

        <div class="plan-meter">
            <div class="plan-meter-head">
                <span>{{ __('plan.meters.numbers') }}</span>
                <b>
                    {{ __('plan.meters.numbers_of', ['used' => $this->business?->isConnected() ? 1 : 0, 'cap' => $this->plan->whatsappNumbers]) }}
                </b>
            </div>
        </div>
        <div class="plan-meter">
            <div class="plan-meter-head">
                <span>{{ __('plan.meters.pace') }}</span>
                <b>{{ __('plan.meters.pace_value', ['cap' => $this->plan->messagesPerHour]) }}</b>
            </div>
        </div>
    </x-ui.card>

    {{-- The owner's AI is the star of the plan: its quota gets its own card, not one more bar. --}}
    @if ($this->plan->allowsAsk)
        <div class="mt-4">
            <x-plan.ask-quota
                :used="$this->askQuestionsUsed"
                :cap="$this->plan->askPerMonth"
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
            <x-ui.button variant="ghost" size="sm" wire:click="cancelScheduledChange">
                {{ __('plan.change.cancel') }}</x-ui.button>
        </div>
    @endif

    @if ($upgradedPlan !== null)
        @php($upgradeTo = Plan::named($upgradedPlan))
        <div class="plan-change-banner is-up mt-4">
            <x-icon name="sparkles" :size="18" />
            <p>
                {{ __('plan.change.upgraded', ['plan' => __('plan.names.'.$upgradedPlan), 'amount' => number_format($this->subscription->upgradeCharge($upgradeTo), 2, ',', '.')]) }}
            </p>
            <x-ui.button variant="primary" size="sm" :href="route('my-payments')" wire:navigate>
                {{ __('plan.change.upload') }}</x-ui.button>
        </div>
    @endif

    <div x-data="{ yearly: false }">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="page-head-title text-xl">{{ __('plan.ladder_title') }}</h2>
            {{-- Same two-months-free toggle the landing sells with; one voice. --}}
            <div class="pricing-period" role="group" aria-label="{{ __('plan.ladder_title') }}">
                <button
                    type="button"
                    class="pricing-period-btn"
                    :class="! yearly && 'is-active'"
                    @click="yearly = false"
                >
                    {{ __('landing.pricing.billing_monthly') }}
                </button>
                <button type="button" class="pricing-period-btn" :class="yearly && 'is-active'" @click="yearly = true">
                    {{ __('landing.pricing.billing_yearly') }}
                    <x-ui.badge variant="brand" ::class="yearly && 'badge-bounce'">
                        {{ __('landing.pricing.billing_yearly_badge') }}
                    </x-ui.badge>
                </button>
            </div>
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
                        <span x-show="! yearly">${{ $tier->price }}<small>{{ __('plan.per_month') }}</small></span>
                        <span x-show="yearly" x-cloak>
                            ${{ $tier->annualMonthlyPrice }}<small>{{ __('landing.pricing.per_month_yearly') }}</small>
                        </span>
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
