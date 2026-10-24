<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Business;
use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * The instances the bridge runs for a business that no active business holds
 * any more. Only names this app mints (`business-{id}`) are judged: the
 * platform's own numbers live on the same bridge and are never ours to free.
 * The answer is remembered with its hour, so a screen shows it without
 * calling the bridge on every render.
 */
class FindOrphanInstances
{
    public const string CACHE_KEY = 'platform:bridge-orphans';

    public function __construct(private readonly EvolutionApi $evolution) {}

    /**
     * @return list<string>
     *
     * @throws ConnectionException|RequestException
     */
    public function handle(): array
    {
        $held = Business::heldInstances();

        $orphans = collect($this->evolution->instanceNames())
            ->filter(fn (string $name): bool => preg_match('/^business-\d+$/', $name) === 1)
            ->reject(fn (string $name): bool => in_array($name, $held, true) || PlatformWhatsApp::owns($name))
            ->values()
            ->all();

        Cache::put(self::CACHE_KEY, ['names' => $orphans, 'at' => now()->toIso8601String()], now()->addDay());

        return $orphans;
    }

    /**
     * What the last look found and when, or null when nobody has looked yet.
     *
     * @return array{names: list<string>, at: CarbonImmutable}|null
     */
    public static function lastKnown(): ?array
    {
        $seen = Cache::get(self::CACHE_KEY);

        return is_array($seen)
            ? ['names' => array_values($seen['names']), 'at' => CarbonImmutable::parse($seen['at'])]
            : null;
    }
}
