<?php

declare(strict_types=1);

use App\Classes\Main\Releases;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Golden rule — the version the customers see is true and says what it brought
|--------------------------------------------------------------------------
| config/releases.php lists every version, newest first; the first one is
| what the footer and both panels print. Each number moves by its kind
| (patch, minor, major) and has its words for «Novedades». The commit hook
| require-version-decision.sh makes every code change decide its number.
*/

const RELEASE_KINDS = ['major', 'minor', 'patch'];

/** @return array{0: int, 1: int, 2: int} */
function semver(string $version): array
{
    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');

    return array_map('intval', explode('.', $version));
}

/** What the previous version becomes after a change of that kind. */
function bumped(string $previous, string $kind): string
{
    [$major, $minor, $patch] = semver($previous);

    return match ($kind) {
        'major' => ($major + 1).'.0.0',
        'minor' => $major.'.'.($minor + 1).'.0',
        'patch' => $major.'.'.$minor.'.'.($patch + 1),
    };
}

test('every version is a valid number with a date, a kind and its words for the customers', function (): void {
    $releases = config('releases');
    // Looked up from the whole list: a dotted version would read as nested keys.
    $words = (array) __('releases.entries');

    expect($releases)->not->toBeEmpty();

    foreach ($releases as $release) {
        semver($release['version']);

        expect($release['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
            ->and(RELEASE_KINDS)->toContain($release['kind'])
            ->and($words[$release['version']]['title'] ?? '')->not->toBe('', "{$release['version']} has no title in lang/es/releases.php")
            ->and($words[$release['version']]['items'] ?? [])->not->toBeEmpty();
    }

    expect((new Releases)->entries[0]['title'])->toBe($words[$releases[0]['version']]['title']);
});

test('versions go newest first, and each one moves by exactly its kind', function (): void {
    $releases = array_reverse(config('releases'));

    // The rule itself, so it is checked even while there is a single version.
    expect(bumped('1.4.2', 'patch'))->toBe('1.4.3')
        ->and(bumped('1.4.2', 'minor'))->toBe('1.5.0')
        ->and(bumped('1.4.2', 'major'))->toBe('2.0.0');

    for ($i = 1; $i < count($releases); $i++) {
        $previous = $releases[$i - 1];
        $release = $releases[$i];

        expect($release['version'])->toBe(bumped($previous['version'], $release['kind']), "{$release['version']} is not a {$release['kind']} after {$previous['version']}")
            ->and($release['date'] >= $previous['date'])->toBeTrue("{$release['version']} is dated before {$previous['version']}");
    }
});

test('no words wait in lang for a version that does not exist', function (): void {
    $versions = array_column(config('releases'), 'version');

    expect(array_keys(__('releases.entries')))->toEqualCanonicalizing($versions);
});

test('the landing footer, both panels and «Novedades» print the current version', function (): void {
    $version = (new Releases)->current;
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->get('/')->assertOk()->assertSeeHtml('data-testid="footer-version"')->assertSee('v'.$version);
    $this->get(route('releases'))->assertOk()->assertSee(__('releases.version', ['version' => $version]))->assertSee(__('releases.current'));

    $client = payingUser();
    $client->assignRole('client');
    $this->actingAs($client)->get(route('dashboard'))->assertSeeHtml('data-testid="sidebar-version"')->assertSee('v'.$version);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh())->get(route('admin.dashboard'))->assertSeeHtml('data-testid="sidebar-version"');
});
