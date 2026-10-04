<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Which model answers which task: a row, so trying a cheaper one is a decision. */
#[Fillable(['key', 'label', 'model_code', 'is_mechanical'])]
class AiTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mechanical' => 'boolean'];
    }

    /**
     * The model assigned to an agent, or null to leave its own attribute alone.
     * Cached: the orchestrator runs on every single generation step.
     *
     * @return array<string, string>
     */
    public static function assignments(): array
    {
        return cache()->remember('ai.tasks', 300, fn (): array => self::query()
            ->whereNotNull('model_code')
            ->pluck('model_code', 'key')
            ->all());
    }

    public static function modelFor(string $key): ?string
    {
        return self::assignments()[$key] ?? null;
    }
}
