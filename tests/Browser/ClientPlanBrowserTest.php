<?php

declare(strict_types=1);

use App\Models\CatalogPhoto;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Mi plan" and the home strip, in a real browser
|--------------------------------------------------------------------------
| Both read the PlanUsage piece the mobile app reads too: this is the look
| of what it feeds, at the widths and in the themes the panel promises.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);

    // A small photo cap and eight photos: the meter sits in its warning band.
    SubscriptionPlan::query()->where('code', 'negocio')->firstOrFail()->update(['catalog_photos' => 10]);

    $this->owner = payingUser();
    $this->owner->assignRole('client');

    app(Tenant::class)->for((int) $this->owner->business_id, function (): void {
        $product = Product::factory()->create(['business_id' => $this->owner->business_id]);

        CatalogPhoto::factory()->count(8)->create([
            'business_id' => $this->owner->business_id,
            'photoable_type' => $product->getMorphClass(),
            'photoable_id' => $product->id,
        ]);
    });

    $this->actingAs($this->owner);
});

test('Mi plan lists the meters in the web order and looks right in both themes and widths', function (string $label, int $width, bool $dark): void {
    $page = visit(route('my-plan'))->resize($width, 1000);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->wait(1)
        ->assertSee('Tu plan actual')
        ->assertSee('8 de 10')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-plan-'.$label);

    $order = json_decode((string) $page->script('JSON.stringify(Array.from(document.querySelectorAll(".plan-meter-head > span")).map(e => e.innerText.trim()))'), true);

    expect($order)->toBe([
        'Conversaciones con la IA',
        'Fotos de catálogo',
        'Minutos de audio transcriptos',
        'Personas en el panel',
        'Ritmo máximo por contacto',
    ]);

    // The page never scrolls sideways.
    expect((bool) $page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue();
})->with([
    'desktop light' => ['desktop-light', 1280, false],
    'desktop dark' => ['desktop-dark', 1280, true],
    'phone light' => ['phone-light', 390, false],
    'phone dark' => ['phone-dark', 390, true],
]);

test('Mi plan says the day the month\'s pace reaches the cap, under its meter, in both themes', function (bool $dark): void {
    // Ten threads ten days into the month against a cap of twenty: one a day, so the cap lands on day 21.
    SubscriptionPlan::query()->where('code', 'negocio')->firstOrFail()->update(['conversations_per_month' => 20]);
    $this->travelTo(now($this->owner->business->localTimezone())->startOfMonth()->addDays(10)->addHour());
    app(Tenant::class)->for((int) $this->owner->business_id, fn () => ConversationMessage::factory()->count(10)->create(['business_id' => $this->owner->business_id]));

    $reachedOn = now($this->owner->business->localTimezone())->startOfMonth()->addDays(20)->format('d/m/Y');

    $page = visit(route('my-plan'))->resize(390, 1000);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->wait(1)
        ->assertSee('Al ritmo de este mes, el tope se alcanza el '.$reachedOn.'.')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-plan-pace-'.($dark ? 'dark' : 'light'));

    expect((bool) $page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue();
})->with([
    'light' => [false],
    'dark' => [true],
]);

test('the home strip shows the conversations meter from the same piece', function (): void {
    visit(route('dashboard'))->resize(1280, 1000)
        ->wait(1)
        ->assertSee('Conversaciones con la IA')
        ->assertSee('0 de 1.000')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'client-home-strip');
});
