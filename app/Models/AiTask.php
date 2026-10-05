<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Which model answers which task: a row, so trying a cheaper one is a decision. */
#[Fillable(['key', 'label', 'model_code', 'fallback_model_code', 'is_mechanical'])]
class AiTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mechanical' => 'boolean'];
    }

    /** A row decides what the agents call, so a saved row drops the cache. */
    protected static function booted(): void
    {
        self::saved(function (): void {
            cache()->forget('ai.tasks');
        });

        self::deleted(function (): void {
            cache()->forget('ai.tasks');
        });
    }

    /**
     * The provider → model ladder of every assigned task, keyed by agent.
     * Cached: this is read on every single prompt.
     *
     * @return array<string, array<string, string>>
     */
    public static function ladders(): array
    {
        return cache()->remember('ai.tasks', 300, function (): array {
            $providers = AiModel::providersByCode();

            return self::query()
                ->whereNotNull('model_code')
                ->get()
                ->mapWithKeys(fn (self $task): array => [$task->key => $task->ladder($providers)])
                ->reject(fn (array $ladder): bool => $ladder === [])
                ->all();
        });
    }

    /**
     * The ladder assigned to an agent, or null to leave the pair written in
     * its attributes alone — which is the state until somebody decides.
     *
     * @return array<string, string>|null
     */
    public static function ladderFor(string $key): ?array
    {
        return self::ladders()[$key] ?? null;
    }

    /**
     * One row per agent for the admin screen: what it is assigned, and what it
     * actually runs on today — which is the agent's own attributes while
     * nothing is assigned. The screen never guesses either one.
     *
     * The ones that talk to a person come first: a model change there is felt
     * by a customer, and the mechanical ones are the cheap half of the list.
     *
     * @return Collection<int, array{id: int, label: string, mechanical: bool, model: string|null, fallback: string|null, running: array<string, string|null>}>
     */
    public static function board(): Collection
    {
        return self::query()->orderBy('is_mechanical')->orderBy('label')->get()->map(fn (self $task): array => [
            'id' => $task->id,
            'label' => $task->label,
            'mechanical' => $task->is_mechanical,
            'model' => $task->model_code,
            'fallback' => $task->fallback_model_code,
            'running' => self::ladderFor($task->key) ?? $task->declaredByAgent(),
        ]);
    }

    /**
     * The provider and model written in the agent's own attributes. Empty when
     * the row names an agent that no longer exists: a renamed class leaves a
     * row behind, and a screen that invents a model would be worse.
     *
     * @return array<string, string|null>
     */
    private function declaredByAgent(): array
    {
        $agent = 'App\\Ai\\Agents\\'.$this->key;

        return class_exists($agent) && method_exists($agent, 'declaredPair') ? $agent::declaredPair() : [];
    }

    /**
     * This task's attempts, in order. The fallback only joins from ANOTHER
     * provider: the package keys the ladder by provider, so a second model of
     * the same lab would simply overwrite the first one. A code with no price
     * row is dropped — an unpriced call is a call nobody can account for.
     *
     * @param  array<string, string>  $providers
     * @return array<string, string>
     */
    private function ladder(array $providers): array
    {
        $ladder = [];

        foreach ([$this->model_code, $this->fallback_model_code] as $code) {
            $provider = $code === null ? null : ($providers[$code] ?? null);

            if ($provider !== null && ! isset($ladder[$provider])) {
                $ladder[$provider] = $code;
            }
        }

        return $ladder;
    }
}
