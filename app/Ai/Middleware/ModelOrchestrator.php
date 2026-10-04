<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Models\AiTask;
use Closure;
use Laravel\Ai\PendingStep;

/**
 * Picks the model a task runs on, from the database instead of the code.
 *
 * `ia-economia-tokens.md` §8 already asks to try a cheap model on mechanical
 * work and MEASURE — which was impossible while the name lived in twelve
 * `#[Model]` attributes. With no row the agent keeps its own attribute, so
 * nothing changes until somebody decides it does.
 */
class ModelOrchestrator
{
    public function handle(PendingStep $step, Closure $next)
    {
        $assigned = AiTask::modelFor(class_basename($this->agentOf($step)));

        return $next($assigned === null ? $step : $step->withModel($assigned));
    }

    /**
     * The agent being run. Read defensively: this is on the hot path of every
     * generation step, and a provider change must never take the panel down.
     */
    private function agentOf(PendingStep $step): string
    {
        return is_object($step->agent ?? null) ? $step->agent::class : (string) ($step->agent ?? '');
    }
}
