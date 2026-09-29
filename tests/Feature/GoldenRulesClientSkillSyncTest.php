<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: the client skill knows every hook it has to satisfy
|--------------------------------------------------------------------------
| A nightly run of the `client` skill closes only with every hook of
| .claude/hooks green (SKILL.md §3.1). A hook registered in settings.json and
| never written into that section is a lock the run does not know about, so the
| skill silently falls behind the enforcement layer it is supposed to obey.
| Fix when red: name the hook in §3.1 of .claude/skills/client/SKILL.md, drop
| the hook that no longer exists, or correct the figure the skill states.
*/

// The only script named in §3.1 that is not a hook: it is what RUNS the skill.
const NON_HOOK_SCRIPTS = ['nightwatch.sh'];

test('every hook registered in settings.json is named in the client skill', function (): void {
    $missing = registeredHooks()
        ->reject(fn (string $hook): bool => str_contains(clientSkillEnforcementSection(), $hook))
        ->sort()
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('every hook the client skill names is a hook that still runs', function (): void {
    $registered = registeredHooks();

    preg_match_all('/[\w-]+\.sh/', clientSkillEnforcementSection(), $named);

    $stale = collect($named[0])
        ->unique()
        ->reject(fn (string $script): bool => in_array($script, NON_HOOK_SCRIPTS, true))
        ->reject(fn (string $script): bool => $registered->contains($script)
            && File::exists(base_path(".claude/hooks/{$script}")))
        ->sort()
        ->values()
        ->all();

    expect($stale)->toBe([]);
});

test('the figures the client skill states match what it is counting', function (): void {
    $skill = File::get(base_path('.claude/skills/client/SKILL.md'));

    // "Los 17 guardianes Write|Edit": the check-* hooks of that matcher, which is
    // every hook there but the non-blocking reminder.
    preg_match('/Los (\d+) guardianes/u', clientSkillEnforcementSection(), $stated);

    expect((int) $stated[1])->toBe(writeEditGuardianCount());

    // "los 12 puntos" (§2), repeated in §3.3 and in the closing rule of §4.
    preg_match_all('/(?:los|estos) (\d+) puntos/u', $skill, $points);

    expect(array_unique($points[1]))->toBe([(string) auditedPointCount()]);
});

/** @return \Illuminate\Support\Collection<int, string> Hook script names, every event. */
function registeredHooks(): Illuminate\Support\Collection
{
    $settings = json_decode(
        File::get(base_path('.claude/settings.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $hooks = collect(data_get($settings, 'hooks.*.*.hooks.*.command'))
        ->map(fn (string $command): string => basename(trim($command)))
        ->unique();

    expect($hooks)->not->toBeEmpty();

    return $hooks;
}

/** The "§3.1 Los hooks … se cumplen" section, where the run's locks are listed. */
function clientSkillEnforcementSection(): string
{
    $skill = File::get(base_path('.claude/skills/client/SKILL.md'));

    // Bounded by the next heading of the same level: naming a hook anywhere else
    // in the skill does not tell the run it must keep that hook green.
    preg_match('/^### 3\.1 .*?(?=^### )/ms', $skill, $section);

    expect($section)->not->toBeEmpty();

    return $section[0];
}

function writeEditGuardianCount(): int
{
    $settings = json_decode(
        File::get(base_path('.claude/settings.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $group = collect(data_get($settings, 'hooks.PostToolUse', []))
        ->firstWhere('matcher', 'Write|Edit');

    return collect(data_get($group, 'hooks.*.command'))
        ->filter(fn (string $command): bool => str_starts_with(basename($command), 'check-'))
        ->count();
}

function auditedPointCount(): int
{
    $skill = File::get(base_path('.claude/skills/client/SKILL.md'));

    preg_match('/^## 2\. .*?(?=^## )/ms', $skill, $section);

    expect($section)->not->toBeEmpty();

    return preg_match_all('/^\d+\. /m', $section[0]);
}
