<?php

use App\Classes\Main\Plan;
use App\Models\Business;
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
    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    #[Computed]
    public function plan(): Plan
    {
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
        $mine = $this->plan;

        return [
            [
                'label' => __('plan.features.conversations', ['cap' => number_format($tier->conversationsPerMonth, 0, ',', '.')]),
                'locked' => $tier->conversationsPerMonth > $mine->conversationsPerMonth,
            ],
            [
                'label' => trans_choice('plan.features.numbers', $tier->whatsappNumbers, ['cap' => $tier->whatsappNumbers]),
                'locked' => $tier->whatsappNumbers > $mine->whatsappNumbers,
            ],
            [
                'label' => __('plan.features.pace', ['cap' => $tier->messagesPerHour]),
                'locked' => $tier->messagesPerHour > $mine->messagesPerHour,
            ],
            $tier->allowsAudio
                ? [
                    'label' => __('plan.features.audio', ['cap' => $tier->audioMinutesPerMonth]),
                    'locked' => $tier->audioMinutesPerMonth > $mine->audioMinutesPerMonth,
                ]
                : ['label' => __('plan.features.audio_none'), 'locked' => false],
        ];
    }

    /** No payments yet: upgrading talks to sales, mirroring the landing's CTA. */
    public function upgradeLink(Plan $tier): ?string
    {
        $number = config('atendia.sales_whatsapp');

        if (! $number) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.urlencode(__('plan.cta_text', ['plan' => __('plan.names.'.$tier->code)]));
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
            <p class="plan-tier-price">
                ${{ $this->plan->price }}<small>{{ __('plan.per_month') }}</small>
            </p>
        </div>

        <div
            class="plan-meter"
            data-state="{{ $this->meterState($this->conversationsUsed, $this->plan->conversationsPerMonth) }}"
        >
            <div class="plan-meter-head">
                <span>{{ __('plan.meters.conversations') }}</span>
                <b>
                    {{ __('plan.meters.conversations_of', ['used' => number_format($this->conversationsUsed, 0, ',', '.'), 'cap' => number_format($this->plan->conversationsPerMonth, 0, ',', '.')]) }}
                </b>
            </div>
            <div class="plan-meter-track">
                <div
                    class="plan-meter-fill"
                    style="width: {{ min(100, (int) round($this->conversationsUsed / max(1, $this->plan->conversationsPerMonth) * 100)) }}%"
                ></div>
            </div>
        </div>

        @if ($this->plan->allowsAudio)
            <div
                class="plan-meter"
                data-state="{{ $this->meterState($this->audioMinutesUsed, $this->plan->audioMinutesPerMonth) }}"
            >
                <div class="plan-meter-head">
                    <span>{{ __('plan.meters.audio') }}</span>
                    <b>
                        {{ __('plan.meters.audio_of', ['used' => $this->audioMinutesUsed, 'cap' => $this->plan->audioMinutesPerMonth]) }}
                    </b>
                </div>
                <div class="plan-meter-track">
                    <div
                        class="plan-meter-fill"
                        style="width: {{ min(100, (int) round($this->audioMinutesUsed / max(1, $this->plan->audioMinutesPerMonth) * 100)) }}%"
                    ></div>
                </div>
            </div>
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
                <button
                    type="button"
                    class="pricing-period-btn"
                    :class="yearly && 'is-active'"
                    @click="yearly = true"
                >
                    {{ __('landing.pricing.billing_yearly') }}
                    <x-ui.badge variant="brand" ::class="yearly && 'badge-bounce'">
                        {{ __('landing.pricing.billing_yearly_badge') }}
                    </x-ui.badge>
                </button>
            </div>
        </div>
        <div class="plan-tiers">
        @foreach ($this->ladder as $tier)
            <x-ui.card class="p-6">
                <div class="plan-tier-head">
                    <h3 class="plan-tier-name">{{ __('plan.names.'.$tier->code) }}</h3>
                    @if ($tier->code === $this->plan->code)
                        <x-ui.badge variant="brand">{{ __('plan.yours') }}</x-ui.badge>
                    @elseif ($tier->code === 'negocio')
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

                @if ($this->plan->isBelow($tier))
                    @if ($this->upgradeLink($tier) !== null)
                        <div class="mt-4">
                            <x-ui.button
                                variant="primary"
                                size="sm"
                                :href="$this->upgradeLink($tier)"
                                target="_blank"
                                :fullWidth="true"
                            >
                                {{ __('plan.cta') }}
                            </x-ui.button>
                        </div>
                    @else
                        <p class="text-subtle mt-4 text-xs">{{ __('plan.cta_soon') }}</p>
                    @endif
                @endif
            </x-ui.card>
        @endforeach
        </div>
    </div>
</div>
