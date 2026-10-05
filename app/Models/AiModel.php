<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A model the platform may use, and what it cost from a given day on. */
#[Fillable(['provider', 'code', 'label', 'prompt_per_million', 'cached_per_million', 'completion_per_million', 'effective_from', 'source', 'is_active'])]
class AiModel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'is_active' => 'boolean',
            'prompt_per_million' => 'decimal:4',
            'cached_per_million' => 'decimal:4',
            'completion_per_million' => 'decimal:4',
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
     * The models a task can be sent to, labelled with their lab because the
     * code alone does not say who answers it.
     *
     * @param  string|null  $exceptProvider  Leaves out one lab: a fallback on
     *                                       the failed lab is not a fallback.
     * @return array<string, string>
     */
    public static function assignable(?string $exceptProvider = null): array
    {
        return self::query()
            ->where('is_active', true)
            ->when($exceptProvider !== null, fn ($query) => $query->where('provider', '!=', $exceptProvider))
            ->orderBy('provider')
            ->orderBy('label')
            ->get()
            ->unique('code')
            ->mapWithKeys(fn (self $model): array => [$model->code => "{$model->label} · {$model->provider}"])
            ->all();
    }

    /** One price row to edit, by id. */
    public static function priceRow(int $id): ?self
    {
        return self::query()->whereKey($id)->first();
    }

    /** Which lab answers for a code, straight from the price rows. */
    public static function providerOf(?string $code): ?string
    {
        return $code === null ? null : (self::providersByCode()[$code] ?? null);
    }
}
