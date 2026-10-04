<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A model the platform may use, and what it cost from a given day on. */
#[Fillable(['code', 'label', 'prompt_per_million', 'cached_per_million', 'completion_per_million', 'effective_from', 'source', 'is_active'])]
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
}
