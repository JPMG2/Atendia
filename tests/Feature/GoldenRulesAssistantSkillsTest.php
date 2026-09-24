<?php

declare(strict_types=1);

use App\Interfaces\Main\AssistantSkillTool;
use App\Models\AssistantSkill;
use Database\Seeders\AssistantSkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule — whatever the AI can handle is an assistant skill
|--------------------------------------------------------------------------
| Every tool is a skill, registered in config and seeded, and no agent
| builds its tools by hand: the skills service hands them out, so a
| switched-off skill leaves every assistant. Born at zero (2026-09-24).
*/

/** @return list<class-string> */
function assistantToolClasses(): array
{
    return collect(File::files(app_path('Ai/Tools')))
        ->map(fn ($file): string => 'App\\Ai\\Tools\\'.$file->getFilenameWithoutExtension())
        ->all();
}

test('every tool in app/Ai/Tools is an assistant skill', function (): void {
    $plainTools = collect(assistantToolClasses())
        ->reject(fn (string $class): bool => is_subclass_of($class, AssistantSkillTool::class))
        ->values();

    expect($plainTools->implode("\n"))->toBe('');
});

test('every skill tool is registered in the config map', function (): void {
    $registered = array_values((array) config('atendia.assistant.skills'));

    $unregistered = collect(assistantToolClasses())
        ->reject(fn (string $class): bool => in_array($class, $registered, true))
        ->values();

    expect($unregistered->implode("\n"))->toBe('');
});

test('the config map and the seeded skills name the same keys', function (): void {
    $this->seed(AssistantSkillSeeder::class);

    $configured = collect(array_keys((array) config('atendia.assistant.skills')))->sort()->values()->all();
    $seeded = AssistantSkill::query()->orderBy('key')->pluck('key')->all();

    expect($seeded)->toBe($configured);
});

test('no agent builds a tool by hand', function (): void {
    $tools = collect(assistantToolClasses())->map(fn (string $class): string => class_basename($class));

    $offenders = collect(File::files(app_path('Ai/Agents')))
        ->filter(fn ($file): bool => $tools->contains(fn (string $tool): bool => preg_match('/new\s+'.$tool.'\s*\(/', $file->getContents()) === 1))
        ->map(fn ($file): string => $file->getFilename())
        ->values();

    expect($offenders->implode("\n"))->toBe('');
});
