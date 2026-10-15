<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Business;
use App\Services\EvolutionApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Frees a bridge instance nobody holds. The name that arrives is a request,
 * not a fact: the bridge and the base are asked again, and an instance an
 * active business holds (or one this app never minted) is refused.
 */
class ReleaseBridgeInstance
{
    public function __construct(
        private readonly EvolutionApi $evolution,
        private readonly FindOrphanInstances $orphans,
    ) {}

    /**
     * @return bool false when the instance is not an orphan, so nothing was touched
     *
     * @throws ConnectionException|RequestException
     */
    public function handle(string $instance): bool
    {
        Gate::authorize('businesses.manage');

        if (! in_array($instance, $this->orphans->handle(), true)) {
            return false;
        }

        $this->evolution->logout($instance);
        $this->evolution->deleteInstance($instance);

        Business::forgetInstance($instance);

        $seen = FindOrphanInstances::lastKnown();

        if ($seen !== null) {
            Cache::put(FindOrphanInstances::CACHE_KEY, [
                'names' => array_values(array_diff($seen['names'], [$instance])),
                'at' => $seen['at']->toIso8601String(),
            ], now()->addDay());
        }

        return true;
    }
}
