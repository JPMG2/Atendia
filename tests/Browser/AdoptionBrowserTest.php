<?php

declare(strict_types=1);

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LoginActivity;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Adoption, in a real browser
|--------------------------------------------------------------------------
| A funnel is read at a glance or it is not read: the chips, the rows and the
| days away have to survive the dark theme and a phone, which is where she
| will actually open this.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    // One account per interesting rung, so the screen is never seen empty.
    $stuck = User::factory()->create(['name' => 'Lucía Pérez', 'email' => 'lucia@modista.test']);
    LoginActivity::factory()->create(['user_id' => $stuck->id, 'created_at' => now()->subDays(11)]);

    $catalog = User::factory()->create(['name' => 'Mariana Ortiz', 'email' => 'mariana@laboratoriovida.test']);
    $catalog->business()->associate(Business::factory()->create(['name' => 'Laboratorio Vida']))->save();
    Service::factory()->create(['business_id' => $catalog->business_id, 'name' => 'Ecodoppler']);
    LoginActivity::factory()->create(['user_id' => $catalog->id, 'created_at' => now()->subDays(3)]);
    SupportTicket::factory()->create(['business_id' => $catalog->business_id, 'user_id' => $catalog->id]);

    $done = User::factory()->create(['name' => 'Sofía Ruiz', 'email' => 'sofia@ferreteriasur.test']);
    $done->business()->associate(Business::factory()->create([
        'name' => 'Ferretería Sur',
        'whatsapp_connected_at' => now()->subDays(4),
    ]))->save();
    $conversation = Conversation::factory()->create(['business_id' => $done->business_id]);
    ConversationMessage::factory()->create([
        'business_id' => $done->business_id,
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Assistant,
    ]);
    LoginActivity::factory()->create(['user_id' => $done->id, 'created_at' => now()]);

    $admin = User::factory()->create(['name' => 'Admin', 'email' => 'equipo@atendia.test']);
    $admin->syncRoles(['admin']);
    $this->actingAs($admin);
});

test('the funnel and the stalled accounts read at a glance', function (): void {
    visit('/admin/adopcion')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Dónde se queda la gente')
        ->assertSee('Sin negocio')
        ->assertSee('Laboratorio Vida')
        ->assertSee('Hace 11 días')
        ->screenshot(filename: 'adoption-desktop');
});

test('the full list adds the ones that made it to the end', function (): void {
    visit('/admin/adopcion?ver=all')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('Ferretería Sur')
        ->assertSee('Su asistente contestó')
        ->screenshot(filename: 'adoption-all');
});

test('a row opens into the dates behind its step', function (): void {
    visit('/admin/adopcion')->resize(1280, 900)
        ->assertSee('Cuánto tarda en llegar a cada paso')
        ->click('[data-testid="adp-expand-'.md5('mariana@laboratoriovida.test').'"]')
        ->waitForText('Todavía no')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'adoption-row-open');
});

test('it reads in the dark and on a phone', function (): void {
    visit('/admin/adopcion')->resize(1280, 900)
        ->click('@theme-toggle')
        ->assertNoJavaScriptErrors()
        ->assertSee('Laboratorio Vida')
        ->screenshot(filename: 'adoption-dark');

    visit('/admin/adopcion')->resize(390, 844)
        ->assertNoJavaScriptErrors()
        ->assertSee('Laboratorio Vida')
        ->screenshot(filename: 'adoption-phone');
});
