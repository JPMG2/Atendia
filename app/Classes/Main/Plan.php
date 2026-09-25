<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use RuntimeException;

/**
 * One plan's entitlements, read from the `plans` table: the single source
 * every screen and gate asks — landing cards, "Mi plan", billing — so no two
 * places can ever disagree on a price or a cap.
 */
final class Plan
{
    /** Yearly billing pays ten months: two free, the same promise everywhere. */
    private const int PAID_MONTHS_PER_YEAR = 10;

    /** @param array{price: int, conversations_per_month: int, whatsapp_numbers: int, messages_per_hour: int, audio_minutes_per_month: int, statistics: string, ask_per_month: int, trial_days: ?int, is_featured: bool} $limits */
    private function __construct(
        public readonly string $code,
        private readonly array $limits,
    ) {}

    public int $price {
        get => (int) $this->limits['price'];
    }

    public int $conversationsPerMonth {
        get => (int) $this->limits['conversations_per_month'];
    }

    public int $whatsappNumbers {
        get => (int) $this->limits['whatsapp_numbers'];
    }

    public int $messagesPerHour {
        get => (int) $this->limits['messages_per_hour'];
    }

    public int $audioMinutesPerMonth {
        get => (int) $this->limits['audio_minutes_per_month'];
    }

    public bool $allowsAudio {
        get => $this->audioMinutesPerMonth > 0;
    }

    /** Questions the owner can put to "Ask AtendIa" each month; we pay the model. */
    public int $askPerMonth {
        get => (int) ($this->limits['ask_per_month'] ?? 0);
    }

    public bool $allowsAsk {
        get => $this->askPerMonth > 0;
    }

    /** The trial plan's length; null on every other plan. */
    public ?int $trialDays {
        get => $this->limits['trial_days'] ?? null;
    }

    /** The "Más elegido" card. */
    public bool $isFeatured {
        get => (bool) ($this->limits['is_featured'] ?? false);
    }

    /** counts → patterns → trends: each level answers a bigger question. */
    public string $statisticsLevel {
        get => (string) ($this->limits['statistics'] ?? 'counts');
    }

    /** The statistics gate, in BOTH directions: never serve above the level paid. */
    public function statisticsAtLeast(string $level): bool
    {
        $ladder = ['counts', 'patterns', 'trends'];

        return array_search($this->statisticsLevel, $ladder, true) >= array_search($level, $ladder, true);
    }

    /** An unknown or missing code falls to the FLOOR plan: a typo must never gift Premium. */
    public static function named(?string $code): self
    {
        $plans = self::catalog();
        $code = array_key_exists((string) $code, $plans) ? (string) $code : (string) array_key_first($plans);

        return new self($code, $plans[$code]);
    }

    /** The plan a new business trials; the floor if no row offers a trial. */
    public static function trial(): self
    {
        $code = collect(self::catalog())->search(fn (array $limits): bool => ($limits['trial_days'] ?? 0) > 0);

        return self::named($code === false ? null : $code);
    }

    /**
     * @return array<string, array{price: int, conversations_per_month: int, whatsapp_numbers: int, messages_per_hour: int, audio_minutes_per_month: int, statistics: string, ask_per_month: int, trial_days: ?int, is_featured: bool}>
     *
     * @throws RuntimeException
     */
    private static function catalog(): array
    {
        return SubscriptionPlan::catalog() ?: throw new RuntimeException('The plans table is empty: run PlanSeeder.');
    }

    /**
     * The business's EFFECTIVE plan: a live trial rides its plan and an
     * expired one falls to the floor by itself — no downgrade job to forget.
     */
    public static function for(Business $business): self
    {
        $subscription = $business->subscription;

        if ($subscription === null) {
            return self::named(null);
        }

        // A paying business keeps its plan, grace days included.
        if (in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return self::named($subscription->plan);
        }

        if ($subscription->trial_ends_at !== null && ! $subscription->onTrial()) {
            return self::named(null);
        }

        return self::named($subscription->plan);
    }

    /** @return list<self> Every plan in config order, for the plan screen. */
    public static function ladder(): array
    {
        return array_map(self::named(...), array_keys(self::catalog()));
    }

    /** What a whole year costs: ten months (two free). */
    public int $yearlyPrice {
        get => $this->price * self::PAID_MONTHS_PER_YEAR;
    }

    /** The yearly price shown as its monthly equivalent. */
    public int $annualMonthlyPrice {
        get => (int) round($this->yearlyPrice / 12);
    }

    /** What the yearly plan saves against twelve monthly payments. */
    public int $annualSavings {
        get => $this->price * 12 - $this->yearlyPrice;
    }

    /**
     * The plan's dials as card lines, one builder for every card (landing,
     * "Mi plan"): the figures come from the row, the words from lang. The
     * value lets a card compare two plans line by line (padlocks).
     *
     * @var list<array{key: string, label: string, value: int}>
     */
    public array $features {
        get {
            $levels = ['counts', 'patterns', 'trends'];

            return array_values(array_filter([
                ['key' => 'conversations', 'label' => __('plan.features.conversations', ['cap' => number_format($this->conversationsPerMonth, 0, ',', '.')]), 'value' => $this->conversationsPerMonth],
                ['key' => 'numbers', 'label' => trans_choice('plan.features.numbers', $this->whatsappNumbers, ['cap' => $this->whatsappNumbers]), 'value' => $this->whatsappNumbers],
                ['key' => 'pace', 'label' => __('plan.features.pace', ['cap' => $this->messagesPerHour]), 'value' => $this->messagesPerHour],
                $this->allowsAudio
                    ? ['key' => 'audio', 'label' => __('plan.features.audio', ['cap' => $this->audioMinutesPerMonth]), 'value' => $this->audioMinutesPerMonth]
                    : ['key' => 'audio', 'label' => __('plan.features.audio_none'), 'value' => 0],
                ['key' => 'statistics', 'label' => __('plan.features.statistics.'.$this->statisticsLevel), 'value' => (int) array_search($this->statisticsLevel, $levels, true)],
                $this->allowsAsk
                    ? ['key' => 'ask', 'label' => __('plan.features.ask', ['cap' => $this->askPerMonth]), 'value' => $this->askPerMonth]
                    : null,
            ]));
        }
    }

    /** Meter color for any usage bar: calm, warning from 80%, alarm past the cap. */
    public static function usageState(int $used, int $cap): string
    {
        return match (true) {
            $used >= $cap => 'over',
            $cap > 0 && $used >= (int) ($cap * 0.8) => 'warn',
            default => 'ok',
        };
    }

    /** Drives the padlock: whether $other sits higher on the ladder than this plan. */
    public function isBelow(self $other): bool
    {
        $ladder = array_keys(self::catalog());

        return array_search($this->code, $ladder, true) < array_search($other->code, $ladder, true);
    }
}
