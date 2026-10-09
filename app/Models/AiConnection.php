<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** A named key to a lab: the secret lives in `.env`, this row names it. */
#[Fillable(['key', 'label', 'is_active'])]
class AiConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** A connection decides what the agents call, so a saved row drops the ladders. */
    protected static function booted(): void
    {
        self::saved(fn () => cache()->forget('ai.tasks'));
        self::deleted(fn () => cache()->forget('ai.tasks'));
    }

    /** Whether `.env` carries a credential for this entry of `config('ai.providers')`. */
    public static function configured(string $key): bool
    {
        $config = (array) config("ai.providers.{$key}");

        return filled($config['key'] ?? null) || filled($config['access_key_id'] ?? null);
    }

    /** The lab behind a connection: what its models are published under. */
    public static function driverOf(string $key): ?string
    {
        $driver = config("ai.providers.{$key}.driver");

        return is_string($driver) ? $driver : null;
    }

    /**
     * The connections a task may be sent through: named, switched on and with
     * a credential. One without a key would fail the first call it receives.
     *
     * @return Collection<int, AiConnection>
     */
    public static function usable(): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->filter(fn (self $connection): bool => self::configured($connection->key))
            ->values();
    }

    /** The first usable connection that reaches a lab, or null when none has a key. */
    public static function firstUsableFor(string $driver): ?string
    {
        return self::usable()->first(fn (self $connection): bool => self::driverOf($connection->key) === $driver)?->key;
    }

    /**
     * The labs a new model can be published under: those with at least one
     * usable connection. Offering one with no key would be a model nobody can call.
     *
     * @return array<string, string> driver => driver
     */
    public static function drivers(): array
    {
        return self::usable()
            ->map(fn (self $connection): ?string => self::driverOf($connection->key))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $driver): array => [$driver => $driver])
            ->all();
    }

    /**
     * Every entry of `config('ai.providers')`, with the name and the state the
     * admin reads: the key is never shown, only whether it exists.
     *
     * @return Collection<int, array{key: string, label: string, driver: string|null, configured: bool, active: bool, models: int}>
     */
    public static function board(): Collection
    {
        $rows = self::query()->get()->keyBy('key');
        $perLab = AiModel::query()->get()->unique('code')->countBy('provider');

        return collect(array_keys((array) config('ai.providers')))
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => $rows->get($key)?->label ?? $key,
                'driver' => self::driverOf($key),
                'configured' => self::configured($key),
                'active' => $rows->get($key)?->is_active ?? false,
                'models' => (int) ($perLab[self::driverOf($key)] ?? 0),
            ])
            ->sortByDesc('configured')
            ->values();
    }

    /**
     * What every connection is called, by key. A key nobody named is shown as
     * itself, which is what the caller falls back to.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::query()->pluck('label', 'key')->all();
    }
}
