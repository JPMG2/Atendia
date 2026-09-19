<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;

/**
 * One plan's entitlements, read from config: the single source of truth
 * every gate asks, in BOTH directions — the client reaching above the
 * plan, and the product silently serving what was never paid for.
 */
final class Plan
{
    /** @param array{price: int, conversations_per_month: int, whatsapp_numbers: int, messages_per_hour: int, audio_minutes_per_month: int, statistics: string} $limits */
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
        $plans = (array) config('atendia.plans');
        $code = array_key_exists((string) $code, $plans) ? (string) $code : (string) array_key_first($plans);

        return new self($code, $plans[$code]);
    }

    /**
     * The business's EFFECTIVE plan: a live trial rides its plan and an
     * expired one falls to the floor by itself — no downgrade job to forget.
     */
    public static function for(Business $business): self
    {
        $subscription = $business->subscription;

        if ($subscription === null || ($subscription->trial_ends_at !== null && ! $subscription->onTrial())) {
            return self::named(null);
        }

        return self::named($subscription->plan);
    }

    /** @return list<self> Every plan in config order, for the plan screen. */
    public static function ladder(): array
    {
        return array_map(self::named(...), array_keys((array) config('atendia.plans')));
    }

    /** Annual billing pays ten months (two free), shown as the monthly equivalent. */
    public int $annualMonthlyPrice {
        get => (int) round($this->price * 10 / 12);
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
        $ladder = array_keys((array) config('atendia.plans'));

        return array_search($this->code, $ladder, true) < array_search($other->code, $ladder, true);
    }
}
