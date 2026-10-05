<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A behaviour knob she may turn without a deploy.
 *
 * The row does not get READ by the feature that uses it: it overrides
 * `config('atendia.<key>')` at boot instead. That is what keeps the fifteen
 * existing call sites untouched and the config file honest as the default.
 */
#[Fillable(['key', 'group', 'type', 'value', 'default_value', 'min_value', 'max_value', 'sort_order'])]
class PlatformSetting extends Model
{
    private const string CACHE_KEY = 'platform.settings';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Every override, keyed by its config path. Read once per boot and cached:
     * this runs on EVERY request, so it cannot be a query each time.
     *
     * @return array<string, string>
     */
    public static function overrides(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * The knobs as the screen shows them: by task, in the order she reads.
     *
     * @return Collection<int, self>
     */
    public static function board(): Collection
    {
        return self::query()->orderBy('group')->orderBy('sort_order')->get();
    }

    /**
     * The days of the week for the box, value and label apart.
     *
     * Built here and not in the view: a lang array keyed 1..7 has INTEGER
     * keys, and the combobox reads an integer-keyed array as a plain list, so
     * each option ended up carrying "Lunes" as its value instead of 1.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function weekdayOptions(): array
    {
        return collect(__('admin.settings.weekdays'))
            ->map(fn (string $label, int $day): array => ['value' => (string) $day, 'label' => $label])
            ->values()
            ->all();
    }

    /** Whether this knob still sits where the code left it. */
    public bool $isDefault {
        get => (string) $this->value === (string) $this->default_value;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_value' => 'float',
            'max_value' => 'float',
            'sort_order' => 'integer',
        ];
    }
}
