<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Conversational;

/*
|--------------------------------------------------------------------------
| Golden rule: every agent's cost is chosen, never inherited
|--------------------------------------------------------------------------
| The pattern-checkable half of .ai/guidelines/ia-economia-tokens.md: an
| explicit provider and model on every agent, and bounded memory on every
| conversational one. Mirrored by check-ai-agent-golden-rules.sh.
*/

function agentReflections(): array
{
    return collect(File::files(app_path('Ai/Agents')))
        ->map(fn ($file): ReflectionClass => new ReflectionClass('App\\Ai\\Agents\\'.$file->getFilenameWithoutExtension()))
        ->all();
}

test('every agent declares its provider and model', function (): void {
    $offenders = collect(agentReflections())
        ->filter(fn (ReflectionClass $agent): bool => $agent->getAttributes(Provider::class) === []
            || $agent->getAttributes(Model::class) === [])
        ->map(fn (ReflectionClass $agent): string => $agent->getShortName())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('every conversational agent bounds its memory', function (): void {
    $offenders = collect(agentReflections())
        ->filter(fn (ReflectionClass $agent): bool => $agent->implementsInterface(Conversational::class)
            && ! $agent->hasConstant('MEMORY_LIMIT'))
        ->map(fn (ReflectionClass $agent): string => $agent->getShortName())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
