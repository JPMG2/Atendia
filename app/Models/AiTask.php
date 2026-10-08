<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiCapability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Ai\Ai;

/** Which model answers which task: a row, so trying a cheaper one is a decision. */
#[Fillable(['key', 'label', 'capability', 'connection_key', 'model_code', 'fallback_connection_key', 'fallback_model_code', 'is_mechanical'])]
class AiTask extends Model
{
    /** The task that turns a customer's voice note into text; it is not an agent. */
    public const string TRANSCRIPTION = 'Transcription';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['is_mechanical' => 'boolean', 'capability' => AiCapability::class];
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
     * The connection → model ladder of every assigned task, keyed by agent.
     * Cached: this is read on every single prompt.
     *
     * @return array<string, array<string, string>>
     */
    public static function ladders(): array
    {
        return cache()->remember('ai.tasks', 300, function (): array {
            $labs = AiModel::providersByCode();

            return self::query()
                ->whereNotNull('model_code')
                ->get()
                ->mapWithKeys(fn (self $task): array => [$task->key => $task->ladder($labs)])
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
     * @return Collection<int, array{id: int, label: string, group: string, needs: AiCapability, mechanical: bool, model: string|null, fallback: string|null, running: array<string, string|null>}>
     */
    public static function board(): Collection
    {
        return self::query()->orderBy('is_mechanical')->orderBy('label')->get()->map(fn (self $task): array => [
            'id' => $task->id,
            'label' => $task->label,
            'group' => $task->group(),
            'needs' => $task->capability,
            'mechanical' => $task->is_mechanical,
            'model' => self::pair($task->connection_key, $task->model_code),
            'fallback' => self::pair($task->fallback_connection_key, $task->fallback_model_code),
            'running' => self::ladderFor($task->key) ?? $task->declaredByCode(),
        ]);
    }

    /**
     * The kind of work, which is how the screen orders the tasks: what a
     * customer reads comes first, the mechanical half after, the audio apart.
     */
    public function group(): string
    {
        return match (true) {
            $this->capability === AiCapability::Transcription => 'audio',
            $this->is_mechanical => 'background',
            default => 'conversation',
        };
    }

    /** The "connection|code" value a select carries, or null while unassigned. */
    private static function pair(?string $connection, ?string $code): ?string
    {
        return $connection !== null && $code !== null ? "{$connection}|{$code}" : null;
    }

    /**
     * What this task runs on while nothing is assigned: the pair written in
     * the agent's own attributes, or the package default for the one task
     * that is not an agent. Empty when the row names an agent that no longer
     * exists: a renamed class leaves a row behind, and a screen that invents
     * a model would be worse.
     *
     * @return array<string, string|null>
     */
    private function declaredByCode(): array
    {
        if ($this->key === self::TRANSCRIPTION) {
            $lab = (string) config('ai.default_for_transcription');

            return [$lab => rescue(fn (): string => Ai::transcriptionProvider($lab)->defaultTranscriptionModel(), null, false)];
        }

        $agent = 'App\\Ai\\Agents\\'.$this->key;

        return class_exists($agent) && method_exists($agent, 'declaredPair') ? $agent::declaredPair() : [];
    }

    /**
     * This task's attempts, in order. The fallback only joins through ANOTHER
     * connection: the package keys the ladder by connection name, so the same
     * key twice would overwrite the first attempt. A pair is dropped when its
     * code has no price row (an unpriced call cannot be accounted for), when
     * the connection no longer reaches the model's lab, or when its key is gone.
     *
     * @param  array<string, string>  $labs  Lab of every priced code.
     * @return array<string, string>
     */
    private function ladder(array $labs): array
    {
        $ladder = [];

        // `connection_key` and not `connection`: Eloquent owns that property name.
        foreach ([[$this->connection_key, $this->model_code], [$this->fallback_connection_key, $this->fallback_model_code]] as [$connection, $code]) {
            if ($connection === null || $code === null || isset($ladder[$connection])) {
                continue;
            }

            if (($labs[$code] ?? null) === AiConnection::driverOf($connection) && AiConnection::configured($connection)) {
                $ladder[$connection] = $code;
            }
        }

        return $ladder;
    }
}
