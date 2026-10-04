<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\Menu;
use App\Models\ModerationFlag;
use App\Models\PanelNotification;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SupportTicket;
use App\Models\TeamInvitation;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

/**
 * The panel under test, with the rows it has to survive. Which user signs in
 * and what is on screen both depend on the panel, and `beforeEach` cannot read
 * a dataset argument — so the setup moved into the test itself.
 */
function openPanel(string $panel): void
{
    app()->setLocale('es');
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);

    if ($panel === 'admin') {
        fillTheAdminDesk();

        $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
        $admin->assignRole('admin');

        test()->actingAs($admin->refresh());

        return;
    }

    $business = Business::factory()->create([
        'name' => 'Centro Odontológico Integral del Sur',
        'appointments_enabled' => true,
    ]);

    $owner = User::factory()->create(['name' => 'María Fernanda González Rodríguez']);
    $owner->business()->associate($business)->save();

    fillTheBusiness($business, $owner);

    test()->actingAs($owner);
}

/**
 * The admin desk on a working day. Emptiness is the other guardian's job
 * (`GoldenRulesFreshScreensTest`); this one measures the layout under rows,
 * which is where it gives way.
 */
function fillTheAdminDesk(): void
{
    Embeddings::fake();

    Company::factory()->create(['legal_name' => 'AtendIa Servicios Digitales S.R.L.']);

    $businesses = Business::factory()->count(5)->create();

    $businesses->each(function (Business $business): void {
        Payment::factory()->count(2)->for($business)->create([
            'status' => 'pending',
            'receipt_path' => 'businesses/'.$business->id.'/receipts/transferencia-bancaria.jpg',
        ]);

        SupportTicket::factory()->for($business)->create();
        Testimonial::factory()->for($business)->create();
        ModerationFlag::factory()->for($business)->create();
    });
}

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
dataset('viewports', [
    'phone' => ['phone', 390, 844],
    'tablet' => ['tablet', 900, 1200],
]);

/**
 * Mandate 2 names both themes and only the dark one had ever been looked at.
 * Light is the default, so it is the one every new client meets first.
 */
dataset('themes', [
    'light' => ['light', false],
    'dark' => ['dark', true],
]);

/**
 * Mandates 1 and 2, measured instead of eyeballed: a screen that scrolls
 * sideways hides half of whatever the row was saying. The list comes from the
 * menu, so tomorrow's screen is swept without maintaining an array. Swept the
 * client panel only until 2026-10-03, so the admin tables had never been
 * measured at any width. The shots are the evidence a human still looks at.
 */
test('no screen scrolls sideways on a small screen', function (string $panel, string $label, int $width, int $height, string $theme, bool $flip): void {
    openPanel($panel);

    $routes = panelRoutes($panel);

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
            ->screenshot(filename: $panel.'-'.$label.'-'.$theme.'-'.str_replace('.', '-', $name));

        $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

        if ((int) $overflow > 0) {
            $wide[] = $name.' overflows by '.$overflow.'px';
        }
    }

    expect($wide)->toBe([]);
})->with('panels')->with('viewports')->with('themes');
