<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AiTask;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;
use ReflectionClass;

/**
 * Where an agent runs: the `ai_tasks` row when there is one, and otherwise the
 * pair written in its own #[Provider] / #[Model] attributes.
 *
 * The package prefers a `provider()` method over the attribute, and a
 * provider => model array IS its failover ladder — so a row can move a task to
 * another lab, which overriding the model alone could never do.
 */
trait RunsAssignedModel
{
    /**
     * The providers to try, in order, each with the model to ask it for.
     *
     * @return array<string, string|null>
     */
    public function provider(): array
    {
        return AiTask::ladderFor(class_basename(static::class)) ?? static::declaredPair();
    }

    /**
     * The pair this class declares, read by reflection exactly as the package
     * reads it. Static so the admin screen can show it.
     *
     * @return array<string, string|null>
     */
    public static function declaredPair(): array
    {
        $reflection = new ReflectionClass(static::class);

        $provider = ($reflection->getAttributes(Provider::class)[0] ?? null)?->newInstance()->value;
        $model = ($reflection->getAttributes(Model::class)[0] ?? null)?->newInstance()->value;

        if (is_array($provider)) {
            return $provider;
        }

        $lab = $provider instanceof Lab ? $provider->value : (string) ($provider ?? config('ai.default'));

        return [$lab => $model];
    }
}
