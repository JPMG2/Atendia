<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiCapability;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A model the platform may use, and what it cost from a given day on. */
#[Fillable(['provider', 'capability', 'code', 'label', 'prompt_per_million', 'cached_per_million', 'completion_per_million', 'per_minute', 'effective_from', 'source', 'is_active'])]
class AiModel extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'capability' => AiCapability::class,
            'effective_from' => 'date',
            'is_active' => 'boolean',
            'prompt_per_million' => 'decimal:4',
            'cached_per_million' => 'decimal:4',
            'completion_per_million' => 'decimal:4',
            'per_minute' => 'decimal:4',
        ];
    }

    /** A price row carries the provider too, so saving one drops the cache. */
    protected static function booted(): void
    {
        self::saved(function (): void {
            cache()->forget('ai.models.providers');
            cache()->forget('ai.tasks');
        });

        self::deleted(function (): void {
            cache()->forget('ai.models.providers');
            cache()->forget('ai.tasks');
        });
    }

    /**
     * Which provider answers for each model code. Cached: every prompt reads
     * it to know WHERE to send the model name — the name alone says nothing,
     * and sending an Anthropic one to OpenAI is a 400.
     *
     * @return array<string, string>
     */
    public static function providersByCode(): array
    {
        return cache()->remember('ai.models.providers', 300, fn (): array => self::query()
            ->distinct()
            ->pluck('provider', 'code')
            ->all());
    }

    /**
     * The price that ruled on a date: the newest one that had already started.
     * A call from March is valued at March's price even if the model is more
     * expensive today — that is the whole reason this table exists.
     */
    public static function rateOn(string $code, CarbonInterface $day): ?self
    {
        return self::query()
            ->where('code', $code)
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Every price of every model, keyed by code, newest first. The cost report
     * walks thousands of rows: it reads this once instead of querying per call.
     *
     * @return Collection<string, Collection<int, AiModel>>
     */
    public static function priceBook(): Collection
    {
        return self::query()->orderByDesc('effective_from')->get()->groupBy('code');
    }

    /** What the codes are called, for a select. */
    public static function options(): array
    {
        return self::query()->where('is_active', true)->orderBy('label')->pluck('label', 'code')->all();
    }

    /**
     * Every price row for the admin table, newest price of each code first.
     *
     * @return Collection<int, AiModel>
     */
    public static function board(): Collection
    {
        return self::query()->orderBy('code')->orderByDesc('effective_from')->get();
    }

    /**
     * What a task can be sent to: every model that can do the work, through
     * every connection that reaches its lab. The value is "connection|code"
     * because the same model through two keys is two different choices — they
     * do not share a bill or a rate limit.
     *
     * @param  string|null  $exceptConnection  Leaves out one connection: a
     *                                         fallback behind the same key is
     *                                         not a fallback.
     * @return array<string, string>
     */
    public static function assignable(AiCapability $needs, ?string $exceptConnection = null): array
    {
        $models = self::query()
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->get()
            ->unique('code')
            ->filter(fn (self $model): bool => $model->capability->serves($needs))
            ->sortBy('label');

        $options = [];

        foreach (AiConnection::usable() as $connection) {
            if ($connection->key === $exceptConnection) {
                continue;
            }

            foreach ($models->where('provider', AiConnection::driverOf($connection->key)) as $model) {
                $options["{$connection->key}|{$model->code}"] = "{$model->label} · {$connection->label}";
            }
        }

        return $options;
    }

    /** One price row to edit, by id. */
    public static function priceRow(int $id): ?self
    {
        return self::query()->whereKey($id)->first();
    }
}
