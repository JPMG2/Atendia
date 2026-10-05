<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Logs\LogReader;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Logs del sistema — the shots a human looks at
|--------------------------------------------------------------------------
| Not a table on purpose: an entry is a block with a trace, and a grid would
| either truncate it or copy tab-separated rubbish. It is a list built to be
| COPIED — which is why the repeat count and the context block matter more
| than any column would.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->logDir = storage_path('logs/browser-probe');

    if (! is_dir($this->logDir)) {
        mkdir($this->logDir, 0775, true);
    }

    file_put_contents($this->logDir.'/laravel.log', <<<'LOG'
[2026-10-05 09:12:01] production.ERROR: SQLSTATE[08006] could not connect to server
#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php(70)
#1 {main}
[2026-10-05 09:12:08] production.ERROR: SQLSTATE[08006] could not connect to server
#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php(70)
#1 {main}
[2026-10-05 09:13:40] production.ERROR: SQLSTATE[08006] could not connect to server
#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php(70)
#1 {main}
[2026-10-05 10:04:00] production.WARNING: El modelo gpt-6-astra tardó 9s en responder
[2026-10-05 11:20:11] production.INFO: El worker de colas arrancó
LOG);

    app()->bind(LogReader::class, fn (): LogReader => new LogReader($this->logDir));

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

afterEach(function (): void {
    @unlink($this->logDir.'/laravel.log');
    @rmdir($this->logDir);
});

test('the logs screen holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.logs'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('could not connect to server')
        // Three identical failures are ONE line that says three.
        ->assertSee('se repitió 3 veces')
        ->assertSee('Copiar todo')
        ->screenshot(filename: 'admin-logs-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'phone light' => ['phone', 390, 844, false],
]);
