<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\HelpArticle;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support, in a real browser
|--------------------------------------------------------------------------
| The panel captures the url, the window and the browser when it opens, and
| that only happens once Alpine runs: a server render cannot prove it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $user = User::factory()->create(['name' => 'María González']);
    $user->business()->associate($this->business)->save();
    $user->assignRole('client');
    $this->actingAs($user);
});

test('the panel opens from the topbar with one field and the screen already picked', function (): void {
    visit('/productos')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="support-open"]')
        ->click('[data-testid="support-open"]')
        ->waitForText(__('support.fields.body'))
        ->assertSee(__('support.kind_legend'))
        ->assertSee(__('support.kinds.problem'))
        ->screenshot(filename: 'support-open-desktop');
});

test('it reads in the dark and on a phone', function (): void {
    visit('/productos')->resize(1280, 900)
        ->click('@theme-toggle')
        ->click('[data-testid="support-open"]')
        ->waitForText(__('support.fields.body'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'support-open-dark');

    visit('/productos')->resize(390, 844)
        ->click('[data-testid="support-open"]')
        ->waitForText(__('support.fields.body'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'support-open-phone');
});

test('the admin inbox lists what arrived, oldest unanswered first', function (): void {
    $admin = User::factory()->create(['name' => 'Admin']);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    SupportTicket::factory()->for($this->business)->create([
        'body' => 'El boton de guardar no responde con el precio vacio',
        'created_at' => now()->subDays(2),
    ]);

    visit('/admin/soporte')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('El boton de guardar no responde')
        ->assertSee('Clínica Vida')
        ->screenshot(filename: 'support-admin-inbox');
});

test('the inbox names the screens that hurt most and opens a reply box', function (): void {
    $admin = User::factory()->create(['name' => 'Admin']);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $first = SupportTicket::factory()->for($this->business)->create([
        'screen' => 'my-products',
        'body' => 'No puedo guardar el precio de un producto nuevo',
        'created_at' => now()->subDay(),
    ]);
    SupportTicket::factory()->for($this->business)->create(['screen' => 'my-products']);

    // An article nobody found useful belongs beside the tickets: it is the
    // one that will produce tomorrow's.
    $this->seed(HelpArticleSeeder::class);
    HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole()
        ->forceFill(['unhelpful_count' => 5, 'helpful_count' => 1, 'opened_count' => 24])->saveQuietly();
    HelpArticle::query()->where('slug', 'cambiar-plan')->sole()
        ->forceFill(['updated_at' => now()->subMonths(9)])->saveQuietly();
    SupportTicket::factory()->for($this->business)->create(['after_help' => true]);

    // The queue is the screen; what the help is doing wrong is one tab over.
    visit('/admin/soporte')->resize(1280, 900)
        ->assertNoJavaScriptErrors()
        ->click(__('support.admin.tabs.help'))
        ->assertSee(__('support.admin.deflection_title'))
        // The grammar has to agree: "1 terminaron" is what this pins.
        ->assertSee('1 terminó en un reporte igual')
        ->assertSee(__('support.admin.stale_title'))
        ->assertSee(__('support.admin.failing_title'))
        ->assertSee('Cómo conecto mi WhatsApp')
        ->assertSee(__('support.admin.pain_title'))
        ->click(__('support.admin.tabs.answer'))
        ->click('[data-testid="sup-expand-'.$first->id.'"]')
        ->waitForText(__('support.admin.reply'))
        ->assertSee(__('support.admin.reply_hint'))
        ->screenshot(filename: 'support-admin-reply');
});
