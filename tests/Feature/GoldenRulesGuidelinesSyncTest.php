<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Golden rule: a written rule actually reaches the agent
|--------------------------------------------------------------------------
| Boost 2.10 started writing Claude Code's guidelines to AGENTS.md: CLAUDE.md
| froze on 2026-09-24 and a new golden rule never reached it. Fix when red:
| `php artisan boost:update` (config/boost.php pins CLAUDE.md).
*/

test('every project guideline is compiled into CLAUDE.md', function (): void {
    $claude = File::get(base_path('CLAUDE.md'));

    $missing = collect(File::files(base_path('.ai/guidelines')))
        ->map(fn ($file): string => $file->getFilenameWithoutExtension())
        ->reject(fn (string $name): bool => str_contains($claude, "=== .ai/{$name} rules ==="))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('Boost never overwrites the hand-written Codex brief', function (): void {
    expect(File::get(base_path('AGENTS.md')))->not->toContain('<laravel-boost-guidelines>');
});
