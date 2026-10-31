<?php

declare(strict_types=1);

use App\Ai\Agents\AskAdmin;
use App\Enums\PlatformEventSeverity;
use App\Models\Business;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The admin's AI assistant, in a real browser
|--------------------------------------------------------------------------
| The button in the topbar, the panel that opens beside the screen, a
| question and its answer with a live link — at the widths and in the themes
| the admin panel promises. The model is faked: what is judged is the screen.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    Company::factory()->create(['brand_name' => 'Aliadito']);

    $admin = User::factory()->create(['name' => 'Juan Pablo', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

test('a Preguntar button on the home opens the panel with that question already asked', function (): void {
    AskAdmin::fake(['Hay 2 comprobantes esperando.']);

    $page = visit('/admin')->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->click('@ask-admin-chip')
        ->assertSee(__('ask_admin.ask.pulse'))
        ->assertSee('Hay 2 comprobantes esperando.')
        ->assertSee(__('ask_admin.today.title'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-admin-from-chip');
});

test('the thread survives a change of screen, and a waiting critical alert sharpens its suggestion', function (): void {
    AskAdmin::fake(['Hay 3 negocios.']);
    PlatformEvent::factory()->severity(PlatformEventSeverity::Critical)->create();

    $page = visit('/admin')->resize(1280, 900);

    $page->click('@ask-admin')
        ->assertSee(trans_choice('ask_admin.suggestions.critical_alert', 1))
        ->assertDontSee(__('ask_admin.suggestions.alerts'))
        ->click(__('ask_admin.suggestions.pulse'))
        ->assertSee('Hay 3 negocios.');

    $again = visit('/admin/negocios')->resize(1280, 900);

    $again->click('@ask-admin')
        ->assertSee(__('ask_admin.suggestions.pulse'))
        ->assertSee('Hay 3 negocios.')
        ->assertSee(__('ask_admin.new_chat'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-admin-thread-kept');
});

test('an e-mail it drafts waits as a card she can correct, and settles into a line once she decides', function (string $label, int $width, bool $dark): void {
    $business = Business::factory()->create(['name' => 'Panadería Sol']);
    User::factory()->count(2)->create(['business_id' => $business->id]);
    AskAdmin::fake([
        AgentResponse::fakeWithPendingApprovals([new PendingApproval('call_mail_1', 'AdminEmailBusiness', [
            'business_id' => $business->id,
            'subject' => 'Tu comprobante está en revisión',
            'message' => 'Hola, recibimos tu comprobante de USD 79,00 y lo estamos verificando. Te avisamos apenas quede acreditado.',
        ])]),
    ]);

    $page = visit('/admin')->resize($width, 900);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->click('@ask-admin')
        ->click(__('ask_admin.suggestions.pulse'))
        ->wait(1)
        ->assertSee(__('ask_admin.draft.title'))
        ->assertSee('Panadería Sol · 2 personas del equipo')
        ->assertSee(__('ask_admin.draft.send'))
        ->assertSee(__('ask_admin.draft.waiting'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-admin-draft-'.$label);

    $page->click(__('ask_admin.draft.drop'))
        ->wait(1.5)
        ->assertSee(__('ask_admin.draft.dropped').' · Tu comprobante está en revisión · Panadería Sol')
        ->assertDontSee(__('ask_admin.draft.title'))
        ->assertSee(__('ask_admin.new_chat'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-admin-draft-settled-'.$label);
})->with([
    'desktop-light' => ['desktop-light', 1280, false],
    'phone-dark' => ['phone-dark', 390, true],
]);

test('the panel closes with Escape and with its X, and opens again', function (): void {
    $page = visit('/admin')->resize(1280, 900);

    $page->click('@ask-admin')
        ->assertSee(__('ask_admin.hello', ['name' => 'Juan']))
        ->keys('#in-question', 'Escape')
        ->wait(1)
        ->assertMissing('.slide-over')
        ->click('@ask-admin')
        ->assertSee(__('ask_admin.hello', ['name' => 'Juan']))
        ->click('[aria-label="'.__('dialog.close').'"]')
        ->wait(1)
        ->assertMissing('.slide-over')
        ->assertNoJavaScriptErrors();
});

test('the button opens the panel, which greets by first name and answers with a link that works', function (string $label, int $width, bool $dark): void {
    AskAdmin::fake(['Hay **3 negocios**. Mira [la lista]('.url('/admin/negocios').') o [otra cosa](https://evil.test/x).']);

    $page = visit('/admin')->resize($width, 900);

    if ($dark) {
        $page->script('document.documentElement.classList.add("dark")');
    }

    $page->assertNoJavaScriptErrors()
        ->assertMissing('.slide-over')
        ->click('@ask-admin')
        ->assertSee(__('ask_admin.hello', ['name' => 'Juan']))
        ->assertSee(__('ask_admin.suggestions.pulse'))
        ->click(__('ask_admin.suggestions.pulse'))
        ->assertSee('Hay 3 negocios.')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'ask-admin-'.$label);

    expect((bool) $page->script('document.querySelectorAll(".ask-msg-ai a.ask-link").length === 1 && document.querySelector(".ask-msg-ai").textContent.includes("otra cosa") && ! document.querySelector(".ask-msg-ai a[href*=evil]")'))->toBeTrue()
        ->and((bool) $page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue()
        ->and((bool) $page->script('(() => { const p = document.querySelector(".slide-over").getBoundingClientRect(); return p.left >= 0 && p.right <= window.innerWidth; })()'))->toBeTrue();
})->with([
    'desktop light' => ['desktop-light', 1280, false],
    'desktop dark' => ['desktop-dark', 1280, true],
    'phone light' => ['phone-light', 390, false],
]);
