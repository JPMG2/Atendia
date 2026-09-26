<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Conversational;

/*
|--------------------------------------------------------------------------
| Golden rule: every agent that talks to a person obeys ONE contract
|--------------------------------------------------------------------------
| The clock and the grounding rules were hand-copied into each agent and had
| drifted (2026-09-26). A conversational agent must interpolate the shared
| AssistantContract, with the clock LAST so the prompt head stays cacheable.
*/

function conversationalAgentSources(): array
{
    return collect(File::files(app_path('Ai/Agents')))
        ->filter(fn ($file): bool => is_subclass_of('App\\Ai\\Agents\\'.$file->getFilenameWithoutExtension(), Conversational::class))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => $file->getContents()])
        ->all();
}

test('every conversational agent interpolates the shared grounding and clock', function (): void {
    $offenders = collect(conversationalAgentSources())
        ->reject(fn (string $source): bool => str_contains($source, 'AssistantContract::for(')
            && str_contains($source, '{$contract->grounding}')
            && str_contains($source, '{$contract->clock}'))
        ->keys()
        ->all();

    expect(conversationalAgentSources())->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});

test('the clock is the last thing in the instructions', function (): void {
    $offenders = collect(conversationalAgentSources())
        ->reject(fn (string $source): bool => preg_match('/\{\$contract->clock\}\s*INSTRUCCIONES;/', $source) === 1)
        ->keys()
        ->all();

    expect($offenders)->toBe([]);
});

test('no agent builds its own clock', function (): void {
    $offenders = collect(File::files(app_path('Ai/Agents')))
        ->filter(fn ($file): bool => preg_match('/Hoy es |FECHA Y HORA|\bnow\(|CarbonImmutable::now/', $file->getContents()) === 1)
        ->map(fn ($file): string => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
