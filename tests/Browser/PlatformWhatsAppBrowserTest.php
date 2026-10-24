<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The platform's WhatsApp line, in a real browser
|--------------------------------------------------------------------------
| Closed with its QR and linked, at the widths and in the themes the admin
| panel promises. The bridge is faked: what is judged here is the screen.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.instance', 'aliadito-plataforma');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

function bridgeForScreen(string $state): void
{
    Http::swap(new HttpFactory);

    // A real QR is a PNG; a one-pixel one is enough to judge the frame around it.
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    Http::fake([
        'http://evolution.test/instance/fetchInstances*' => fn (Request $request) => Http::response(
            str_contains($request->url(), 'instanceName=')
                ? [['name' => 'aliadito-plataforma', 'ownerJid' => '5492995243890@s.whatsapp.net']]
                : [['name' => 'aliadito-plataforma']],
        ),
        'http://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => $state]]),
        'http://evolution.test/instance/connect/*' => Http::response(['base64' => $png]),
    ]);
}

test('closed, it offers the QR and looks right in both themes and widths', function (string $label, int $width, bool $dark): void {
    bridgeForScreen('close');

    $page = visit(route('admin.platform-whatsapp'))->resize($width, 1000);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    $page->wait(2)
        ->assertSee(__('platform_whatsapp.status.closed'))
        ->assertSee(__('platform_whatsapp.notes.down'))
        ->press(__('platform_whatsapp.link.start'))
        ->wait(2)
        ->assertSee(__('platform_whatsapp.link.steps.scan'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'platform-whatsapp-closed-'.$label);

    expect((bool) $page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue()
        ->and((bool) $page->script('!!document.querySelector(".pw-qr") && document.querySelector(".pw-qr").getBoundingClientRect().width >= 190'))->toBeTrue();
})->with([
    'desktop light' => ['desktop-light', 1280, false],
    'desktop dark' => ['desktop-dark', 1280, true],
    'tablet light' => ['tablet-light', 900, false],
    'phone light' => ['phone-light', 390, false],
    'phone dark' => ['phone-dark', 390, true],
]);

test('linked, it says so and offers to unlink, in both themes and widths', function (string $label, int $width, bool $dark): void {
    bridgeForScreen('open');

    $page = visit(route('admin.platform-whatsapp'))->resize($width, 900);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    $page->wait(2)
        ->assertSee(__('platform_whatsapp.status.connected'))
        ->assertSee(__('platform_whatsapp.unlink.action'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'platform-whatsapp-linked-'.$label);

    expect((bool) $page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue();
})->with([
    'desktop light' => ['desktop-light', 1280, false],
    'phone dark' => ['phone-dark', 390, true],
]);

test('unlinking asks first, through the system dialog, and nothing is closed until it is accepted', function (): void {
    bridgeForScreen('open');

    $page = visit(route('admin.platform-whatsapp'))->resize(1280, 900);

    $page->wait(2)
        ->press(__('platform_whatsapp.unlink.action'))
        ->wait(1)
        ->assertSee(__('platform_whatsapp.unlink.confirm_title'))
        ->screenshot(filename: 'platform-whatsapp-unlink-confirm');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
});
