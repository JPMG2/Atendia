<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\HasHighlights;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A row of the plan catalog. Read through App\Classes\Main\Plan, never
 * directly: the catalog is cached whole and every screen asks the same copy.
 */
#[Fillable(['code', 'sort_order', 'price', 'conversations_per_month', 'team_seats', 'messages_per_hour', 'audio_minutes_per_month', 'statistics', 'ask_per_month', 'catalog_photos', 'photos_per_item', 'reads_media', 'departments', 'daily_digest', 'trial_days', 'is_featured', 'ai_alert_share'])]
class SubscriptionPlan extends Model implements DataTable, HasColumns, HasHighlights
{
    use LogsActivity;

    private const string CACHE_KEY = 'plans.catalog';

    /** Dials where more is better: a plan above never gives less than the one below. */
    public const array LADDER = [
        'price', 'conversations_per_month', 'team_seats', 'messages_per_hour',
        'audio_minutes_per_month', 'ask_per_month', 'catalog_photos', 'photos_per_item',
    ];

    /** Switches with the same rule: a feature sold below is never missing above. */
    public const array LADDER_FLAGS = ['reads_media', 'departments', 'daily_digest'];

    private const array STATISTICS_LEVELS = ['counts', 'patterns', 'trends'];

    protected $table = 'plans';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Who changed a price or a cap, and from what to what: the audit screen reads it. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...self::LADDER, ...self::LADDER_FLAGS, 'statistics', 'trial_days', 'is_featured', 'ai_alert_share'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * The plans as the catalog list shows them, floor first.
     *
     * @return Collection<int, array{id: int, code: string, name: string, price: int, price_label: string, conversations: int, conversations_label: string, seats: int, ask: int, businesses: int, featured: bool, trial: ?int, archived: bool, tags: list<array{label: string, tone: string}>}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (self $plan): array => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => __('plan.names.'.$plan->code),
                'price' => (int) $plan->price,
                'price_label' => 'US$ '.(int) $plan->price,
                'conversations' => (int) $plan->conversations_per_month,
                // As the browser's toLocaleString('es') wrote it: Spanish groups from five digits ("1000",
                // "12.345"); PHP's ICU groups from four, so the shorter figures go as they are.
                'conversations_label' => $plan->conversations_per_month < 10000
                    ? (string) $plan->conversations_per_month
                    : (string) Number::format((int) $plan->conversations_per_month, locale: 'es'),
                'seats' => (int) $plan->team_seats,
                'ask' => (int) $plan->ask_per_month,
                'businesses' => Subscription::countOnPlan($plan->code),
                'featured' => (bool) $plan->is_featured,
                'trial' => $plan->trial_days === null ? null : (int) $plan->trial_days,
                'archived' => $plan->archived_at !== null,
                'tags' => $plan->catalogTags(),
            ])
            ->values();
    }

    /**
     * The words the list puts after a plan, each with the tone the web paints it in.
     *
     * @return list<array{label: string, tone: string}>
     */
    private function catalogTags(): array
    {
        return array_values(array_filter([
            $this->is_featured ? ['label' => __('catalog.plan.tags.featured'), 'tone' => 'brand'] : null,
            $this->trial_days !== null ? ['label' => __('catalog.plan.tags.trial', ['days' => (int) $this->trial_days]), 'tone' => 'info'] : null,
            $this->archived_at !== null ? ['label' => __('catalog.plan.tags.archived'), 'tone' => 'neutral'] : null,
        ]));
    }

    /**
     * What each plan brings in today: how many real businesses pay for it and its share of the
     * monthly recurring revenue, the Inicio's own count and sum cut by plan, so the lines add up
     * to the Inicio. An archived plan still charges whoever holds it: it stays while someone pays.
     *
     * @return list<array{when: string, text: string, mono: bool}>
     */
    public function catalogHighlights(): array
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (self $plan): array => ['plan' => $plan, 'paying' => Subscription::payingCount($plan->code)])
            ->filter(fn (array $line): bool => $line['plan']->archived_at === null || $line['paying'] > 0)
            ->map(function (array $line): array {
                $plan = $line['plan'];
                $count = $line['paying'];

                return [
                    'when' => __('plan.names.'.$plan->code),
                    'mono' => false,
                    'text' => trans_choice('catalog.plan.highlights.line', $count, [
                        'count' => $count,
                        'amount' => number_format(Subscription::monthlyRecurringRevenue($plan->code), 2, ',', '.'),
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<CatalogColumnDto> */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('name', __('catalog.plan.columns.plan'), CatalogCell::Name, fill: true, search: true, searchAlso: ['code'], codeBelow: 'code'),
            new CatalogColumnDto('price_label', __('catalog.plan.columns.price'), CatalogCell::Number),
            new CatalogColumnDto('conversations_label', __('catalog.plan.columns.conversations'), CatalogCell::Number),
            new CatalogColumnDto('ask', __('catalog.plan.columns.ask'), CatalogCell::Number),
            new CatalogColumnDto('businesses', __('catalog.plan.columns.businesses'), CatalogCell::Number),
            new CatalogColumnDto('tags', __('catalog.plan.columns.tags'), CatalogCell::Badges),
        ];
    }

    /**
     * The check that keeps the ladder a ladder: against the plan right below and
     * right above this one, a figure may not go the wrong way. Nothing sells
     * "Premium" with fewer conversations than the plan under it.
     */
    public static function ladderRule(?int $id, string $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($id, $field): void {
            // Only what is sold is a ladder: an archived plan sits outside it.
            $plans = self::query()->whereNull('archived_at')->orderBy('sort_order')->get();
            $at = $plans->search(fn (self $plan): bool => $plan->id === $id);

            if ($at === false) {
                return;
            }

            $below = $at > 0 ? $plans[$at - 1] : null;
            $above = $plans->get($at + 1);
            $flag = in_array($field, self::LADDER_FLAGS, true);
            $rank = fn (self $plan): int => $field === 'statistics'
                ? (int) array_search($plan->statistics, self::STATISTICS_LEVELS, true)
                : (int) $plan->{$field};
            $mine = match (true) {
                $flag => (int) filter_var($value, FILTER_VALIDATE_BOOLEAN),
                $field === 'statistics' => (int) array_search($value, self::STATISTICS_LEVELS, true),
                default => (int) $value,
            };

            if ($below !== null && $mine < $rank($below)) {
                $fail($flag
                    ? __('catalog.plan.ladder.flag_below', ['plan' => __('plan.names.'.$below->code)])
                    : __('catalog.plan.ladder.min', ['plan' => __('plan.names.'.$below->code), 'value' => $field === 'statistics' ? __('catalog.plan.statistics.'.$below->statistics) : $rank($below)]));
            }

            if ($above !== null && $mine > $rank($above)) {
                $fail($flag
                    ? __('catalog.plan.ladder.flag_above', ['plan' => __('plan.names.'.$above->code)])
                    : __('catalog.plan.ladder.max', ['plan' => __('plan.names.'.$above->code), 'value' => $field === 'statistics' ? __('catalog.plan.statistics.'.$above->statistics) : $rank($above)]));
            }
        };
    }

    /**
     * Every plan by code, floor first. Cached until a row changes.
     *
     * @return array<string, array{price: int, conversations_per_month: int, team_seats: int, messages_per_hour: int, audio_minutes_per_month: int, statistics: string, ask_per_month: int, catalog_photos: int, photos_per_item: int, reads_media: bool, departments: bool, daily_digest: bool, trial_days: ?int, is_featured: bool, ai_alert_share: int, archived: bool}>
     */
    public static function catalog(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (self $plan): array => [$plan->code => [
                'price' => (int) $plan->price,
                'conversations_per_month' => (int) $plan->conversations_per_month,
                'team_seats' => (int) $plan->team_seats,
                'messages_per_hour' => (int) $plan->messages_per_hour,
                'audio_minutes_per_month' => (int) $plan->audio_minutes_per_month,
                'statistics' => (string) $plan->statistics,
                'ask_per_month' => (int) $plan->ask_per_month,
                'catalog_photos' => (int) $plan->catalog_photos,
                'photos_per_item' => (int) $plan->photos_per_item,
                'reads_media' => (bool) $plan->reads_media,
                'departments' => (bool) $plan->departments,
                'daily_digest' => (bool) $plan->daily_digest,
                'trial_days' => $plan->trial_days === null ? null : (int) $plan->trial_days,
                'is_featured' => (bool) $plan->is_featured,
                'ai_alert_share' => (int) $plan->ai_alert_share,
                'archived' => $plan->archived_at !== null,
            ]])
            ->all());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'reads_media' => 'boolean', 'departments' => 'boolean', 'daily_digest' => 'boolean', 'archived_at' => 'datetime'];
    }
}
