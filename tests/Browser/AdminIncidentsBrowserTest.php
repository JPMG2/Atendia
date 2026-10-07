<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Enums\CustomerSentiment;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Qué salió mal — the shots a human looks at
|--------------------------------------------------------------------------
| Six columns and one of them a quote from a customer, which is where this
| layout gives way: a long business name beside a long message is what turns
| a readable desk into a sideways scroll nobody discovers.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $longest = Business::factory()->create(['name' => 'Centro Odontológico Integral del Sur']);
    $lab = Business::factory()->create(['name' => 'Laboratorio Vida']);

    // Unanswered, with the longest message of the lot: the worst row there is.
    $ignored = Conversation::factory()->create([
        'business_id' => $longest->id,
        'contact_name' => 'María Esther Gutiérrez',
        'last_message_at' => now()->subHours(3),
    ]);
    ConversationMessage::factory()->create([
        'business_id' => $longest->id,
        'conversation_id' => $ignored->id,
        'direction' => MessageDirection::In,
        'body' => 'Buenas tardes, necesito saber si tienen turno para una radiografía panorámica esta semana y cuánto sale con obra social',
        'created_at' => now()->subHours(3),
    ]);

    // A second one of the SAME business and the SAME problem: that is what
    // turns two accidents into a business failing, and the desk has to say it.
    $alsoIgnored = Conversation::factory()->create([
        'business_id' => $longest->id,
        'contact_name' => 'Rubén Sosa',
        'last_message_at' => now()->subMinutes(40),
    ]);
    ConversationMessage::factory()->create([
        'business_id' => $longest->id,
        'conversation_id' => $alsoIgnored->id,
        'direction' => MessageDirection::In,
        'body' => 'Hola, me atienden?',
        'created_at' => now()->subMinutes(40),
    ]);

    // Handed to a person, and nobody came.
    $handed = Conversation::factory()->create([
        'business_id' => $lab->id,
        'contact_name' => 'Jorge Pérez',
        'status' => ConversationStatus::Team,
        'escalated_at' => now()->subHours(5),
        'last_message_at' => now()->subHours(5),
    ]);
    ConversationMessage::factory()->create([
        'business_id' => $lab->id,
        'conversation_id' => $handed->id,
        'direction' => MessageDirection::In,
        'body' => 'Quiero hablar con una persona',
        'created_at' => now()->subHours(5),
    ]);

    // Read as annoyed: the softest row, and it still has to be reachable.
    $upset = Conversation::factory()->create([
        'business_id' => $lab->id,
        'contact_name' => 'Carla Benítez',
        'last_message_at' => now()->subHours(8),
    ]);
    $message = ConversationMessage::factory()->create([
        'business_id' => $lab->id,
        'conversation_id' => $upset->id,
        'direction' => MessageDirection::In,
        'created_at' => now()->subHours(8),
    ]);
    ConversationAnalysis::query()->create([
        'business_id' => $lab->id,
        'conversation_id' => $upset->id,
        'first_message_id' => $message->id,
        'last_message_id' => $message->id,
        'sentiment' => CustomerSentiment::Negative,
    ]);

    // A job with no business behind it: its row has to read as platform.
    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => "RuntimeException: el puente de WhatsApp no respondió en 30 s\n#0 /var/www/html/app/Jobs/SendWhatsAppMessage.php(64)",
        'failed_at' => now()->subMinutes(25),
    ]);

    $admin = User::factory()->create(['name' => 'Administración de la plataforma']);
    $admin->assignRole('admin');

    $this->actingAs($admin->refresh());
});

test('the incidents desk holds at every width in both themes', function (string $label, int $width, int $height, bool $dark): void {
    $page = visit(route('admin.incidents'))->resize($width, $height);

    if ($dark) {
        $page->click('@theme-toggle');
    }

    $page->assertNoJavaScriptErrors()
        ->assertSee('Qué salió mal')
        // It opens on the worst thing that has rows, not on the first tab.
        ->assertSee('La IA no contestó')
        ->assertSee('Centro Odontológico Integral del Sur')
        ->assertSee('María Esther Gutiérrez')
        // The pattern behind the rows, named above them. Two of the three are
        // the same business: the third belongs to another one.
        ->assertSee('Centro Odontológico Integral del Sur concentra 2 de 3.')
        ->assertPresent('.inc-excerpt')
        ->screenshot(filename: 'admin-incidents-'.$label.'-'.($dark ? 'dark' : 'light'));

    $overflow = $page->script('document.documentElement.scrollWidth - window.innerWidth');

    expect((int) $overflow)->toBeLessThanOrEqual(0);

    // The page not overflowing says nothing about the table: a column cut off
    // inside its own card hides behind a scrollbar nobody discovers.
    $cut = $page->script(
        'Array.from(document.querySelectorAll(".pay-table-wrap"))
            .map(w => w.scrollWidth - w.clientWidth)
            .reduce((a, b) => Math.max(a, b), 0)'
    );

    expect((int) $cut)->toBe(0);
})->with([
    'desktop light' => ['desktop', 1280, 900, false],
    'desktop dark' => ['desktop', 1280, 900, true],
    'tablet light' => ['tablet', 900, 900, false],
    'phone light' => ['phone', 390, 844, false],
    'phone dark' => ['phone', 390, 844, true],
]);

test('with nothing wrong the desk says so, which is what she sees most days', function (): void {
    Conversation::query()->delete();
    DB::table('failed_jobs')->delete();

    visit(route('admin.incidents'))->resize(1280, 900)
        ->assertSee('Nada por atender')
        ->assertSee('Esta pantalla vacía es la buena noticia.')
        ->assertMissing('.pay-table')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-incidents-empty');
});

test('each tab shows its own rows, and the platform job reads as nobody business', function (): void {
    $page = visit(route('admin.incidents'))->resize(1280, 900);

    $page->click('Se cayó')
        ->assertSee('De la plataforma')
        ->assertSee('RuntimeException: el puente de WhatsApp no respondió en 30 s')
        ->assertSee('Ver el log')
        ->screenshot(filename: 'admin-incidents-jobs');

    $page->click('Derivadas')
        ->assertSee('Jorge Pérez')
        ->assertSee('Laboratorio Vida')
        ->assertSee('Ver el negocio')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'admin-incidents-handoff');
});
