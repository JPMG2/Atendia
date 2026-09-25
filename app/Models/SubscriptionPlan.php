<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A row of the plan catalog. Read through App\Classes\Main\Plan, never
 * directly: the catalog is cached whole and every screen asks the same copy.
 */
#[Fillable(['code', 'sort_order', 'price', 'conversations_per_month', 'whatsapp_numbers', 'messages_per_hour', 'audio_minutes_per_month', 'statistics', 'ask_per_month', 'trial_days', 'is_featured'])]
class SubscriptionPlan extends Model
{
    private const string CACHE_KEY = 'plans.catalog';

    protected $table = 'plans';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Every plan by code, floor first. Cached until a row changes.
     *
     * @return array<string, array{price: int, conversations_per_month: int, whatsapp_numbers: int, messages_per_hour: int, audio_minutes_per_month: int, statistics: string, ask_per_month: int, trial_days: ?int, is_featured: bool}>
     */
    public static function catalog(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (self $plan): array => [$plan->code => [
                'price' => (int) $plan->price,
                'conversations_per_month' => (int) $plan->conversations_per_month,
                'whatsapp_numbers' => (int) $plan->whatsapp_numbers,
                'messages_per_hour' => (int) $plan->messages_per_hour,
                'audio_minutes_per_month' => (int) $plan->audio_minutes_per_month,
                'statistics' => (string) $plan->statistics,
                'ask_per_month' => (int) $plan->ask_per_month,
                'trial_days' => $plan->trial_days === null ? null : (int) $plan->trial_days,
                'is_featured' => (bool) $plan->is_featured,
            ]])
            ->all());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }
}
