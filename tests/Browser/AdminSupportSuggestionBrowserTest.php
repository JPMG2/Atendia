<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Soporte — the AI's reading beside the report, the shots a human looks at
|--------------------------------------------------------------------------
| A card between the report and the answer box: what the AI understood, a
| draft built only from the help, and one button that pastes it for her to
| edit. It must hold on a phone and in the dark, and say plainly that the
| business sees none of it until she sends.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(HelpArticleSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);

    $this->ticket = SupportTicket::factory()->for(Business::factory()->create(['name' => 'Clínica Vida']))->create([
        'screen' => 'whatsapp',
        'body' => 'Mi asistente dejó de responder desde ayer y la barra dice WhatsApp sin conectar. No sé qué hacer, tengo clientes esperando.',
        'ai_suggestion' => [
            'summary' => 'El WhatsApp se desconectó y el asistente no responde.',
            'kind' => 'problem',
            'priority' => 'high',
            'draft' => "Casi siempre es la sesión de WhatsApp que se cerró.\n1. Entra a WhatsApp en el panel.\n2. Toca \"Conectar mi WhatsApp\" y escanea el código otra vez.",
            'used' => ['whatsapp-se-desconecto'],
            'confidence' => 'high',
            'reason' => 'El artículo responde exactamente esto.',
            'looks_like' => 'K7M2PQ',
        ],
        'ai_suggested_at' => now(),
    ]);

    $admin = User::factory()->create(['name' => 'Juan', 'email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());
});

test('the suggestion card holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.support', ['reporte' => $this->ticket->id]))->resize($width, $height);

    // The open panel covers the topbar, so the theme is set the way the toggle does it.
    if ($dark) {
        $page->script("localStorage.setItem('atendia-theme', 'dark'); document.documentElement.classList.add('dark'); document.documentElement.dataset.theme = 'dark';");
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Sugerencia de la IA')
        ->assertSee('Respaldada por la ayuda')
        ->assertSee('Se parece al reporte K7M2PQ')
        ->assertSee('no le llega al negocio hasta que la envíes')
        ->screenshot(filename: 'admin-support-ai-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);
})->with([
    'desktop light' => ['desktop', 1280, 1000, false],
    'desktop dark' => ['desktop', 1280, 1000, true],
    'phone light' => ['phone', 390, 1200, false],
    'phone dark' => ['phone', 390, 1200, true],
]);

test('using the draft puts it in the answer box and sends nothing', function (): void {
    $page = visit(route('admin.support', ['reporte' => $this->ticket->id]))->resize(1280, 1000);

    $page->script("document.querySelector('[data-testid=ai-use-draft]').click()");
    $page->wait(1)->assertNoJavaScriptErrors();

    $typed = $page->script("document.querySelector('[name=body]').value");

    expect($typed)->toContain('Conectar mi WhatsApp');
    expect($this->ticket->fresh()->reply)->toBeNull()
        ->and($this->ticket->fresh()->answered_at)->toBeNull();

    $page->screenshot(filename: 'admin-support-ai-used');
});
