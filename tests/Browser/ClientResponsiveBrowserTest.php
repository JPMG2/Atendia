<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\Menu;
use App\Models\PanelNotification;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $business = Business::factory()->create([
        'name' => 'Centro Odontológico Integral del Sur',
        'appointments_enabled' => true,
    ]);

    $owner = User::factory()->create(['name' => 'María Fernanda González Rodríguez']);
    $owner->business()->associate($business)->save();

    fillTheBusiness($business, $owner);

    $this->actingAs($owner);
});

/**
 * Rows, not emptiness. Every capture until 2026-09-30 was taken on a business
 * with nothing loaded, so the tables, the lists and the cards with real content
 * were never measured at any width — and that is where a layout gives way.
 */
function fillTheBusiness(Business $business, User $owner): void
{
    // Saving knowledge indexes it; the real embedding call has no business here.
    Embeddings::fake();

    $category = ServiceCategory::factory()->for($business)->create(['name' => 'Odontología general']);

    Service::factory()->count(6)->for($business)->create([
        'service_category_id' => $category->id,
        'is_active' => true,
    ]);
    Product::factory()->count(6)->for($business)->create(['is_active' => true]);

    $customers = Customer::factory()->count(5)->for($business)->create();

    $customers->each(function (Customer $customer) use ($business): void {
        $thread = Conversation::factory()->for($business)->create([
            'contact_name' => $customer->name,
            'contact_phone' => $customer->phone,
        ]);

        ConversationMessage::factory()->count(4)->for($business)->create(['conversation_id' => $thread->id]);
    });

    Appointment::factory()->count(3)->for($business)->create([
        'customer_id' => $customers->first()->id,
        'starts_at' => now()->addHours(2),
    ]);

    KnowledgeDocument::factory()->count(4)->for($business)->create();
    Payment::factory()->count(3)->for($business)->create();
    PanelNotification::factory()->count(4)->for($business)->create();

    TeamInvitation::factory()->for($business)->create(['email' => 'recepcion@clinica.test']);

    $owner->refresh();
}

/**
 * The two widths the design mandate names. The phone is the drawer layout; 900px
 * is the tightest the DESKTOP layout ever gets — the 264px sidebar still holds
 * its width there and the work area lives on what is left, which is why it needs
 * its own measurement instead of being assumed fine because the phone passed.
 */
dataset('client viewports', [
    'phone' => ['phone', 390, 844],
    'tablet' => ['tablet', 900, 1200],
]);

/**
 * Mandate 2 names both themes and only the dark one had ever been looked at.
 * Light is the default, so it is the one every new client meets first.
 */
dataset('client themes', [
    'light' => ['light', false],
    'dark' => ['dark', true],
]);

/**
 * Mandates 1 and 2 of the design system, measured instead of eyeballed: a screen
 * that scrolls sideways hides half of whatever the row was saying, and nobody
 * goes looking for it. The list comes from the menu, so a screen added tomorrow
 * is swept without anyone maintaining an array here. The dark shot of each one
 * is the evidence a human still has to look at.
 */
test('no client screen scrolls sideways on a small screen', function (string $label, int $width, int $height, string $theme, bool $flip): void {
    $routes = Menu::query()
        ->where('panel', 'client')
        ->whereNotNull('route_name')
        ->pluck('route_name')
        ->unique()
        ->values();

    expect($routes)->not->toBeEmpty();

    $wide = [];

    foreach ($routes as $name) {
        $page = visit(route($name))->resize($width, $height);

        // The toggle is per page: a fresh context does not carry localStorage,
        // so light needs no click and dark needs one on every screen.
        if ($flip) {
            $page->click('@theme-toggle');
        }

        $page->assertNoJavaScriptErrors()
            ->screenshot(filename: $label.'-'.$theme.'-'.str_replace('.', '-', $name));

        $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

        if ((int) $overflow > 0) {
            $wide[] = $name.' overflows by '.$overflow.'px';
        }
    }

    expect($wide)->toBe([]);
})->with('client viewports')->with('client themes');
