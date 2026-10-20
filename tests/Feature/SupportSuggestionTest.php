<?php

declare(strict_types=1);

use App\Actions\Support\CreateSupportTicket;
use App\Actions\Support\SuggestForSupportTicket;
use App\Ai\Agents\SupportTicketReviewer;
use App\Enums\SupportTicketStatus;
use App\Jobs\PrepareSupportSuggestion;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The AI's first reading of a report
|--------------------------------------------------------------------------
| What it prepares is for the team only. The draft may lean on the help and
| nothing else, so what these tests pin is the fence around the model: when it
| is not asked, what it is given, and what is thrown away from what it says.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HelpArticleSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);

    // Off in the whole suite, so no test ever reaches the provider by accident.
    config()->set('atendia.support.ai_suggestions', 1);

    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $this->ticket = SupportTicket::factory()->create([
        'business_id' => $this->business->id,
        'screen' => 'whatsapp',
        'body' => 'Mi asistente dejó de responder desde ayer y la barra dice WhatsApp sin conectar.',
    ]);
});

function reading(array $overrides = []): array
{
    return array_merge([
        'summary' => 'El WhatsApp se desconectó y el asistente no responde.',
        'kind' => 'problem',
        'priority' => 'high',
        'draft' => 'Casi siempre es la sesión de WhatsApp que se cerró. Entra a WhatsApp y toca "Conectar mi WhatsApp".',
        'used' => ['whatsapp-se-desconecto'],
        'confidence' => 'high',
        'reason' => 'El artículo responde exactamente esto.',
    ], $overrides);
}

test('a new report asks for its reading off the request that filed it', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    app(CreateSupportTicket::class)->handle(
        $user,
        'Mi asistente dejó de responder desde ayer.',
        SupportTicketKind::query()->where('code', 'problem')->firstOrFail(),
        'whatsapp',
        [],
    );

    Queue::assertPushed(PrepareSupportSuggestion::class);
});

test('a reading and a draft backed by an article are stored on the report', function (): void {
    SupportTicketReviewer::fake([reading()]);

    $suggestion = app(SuggestForSupportTicket::class)->handle($this->ticket);

    expect($suggestion)->toMatchArray([
        'kind' => 'problem',
        'priority' => 'high',
        'used' => ['whatsapp-se-desconecto'],
        'confidence' => 'high',
    ])
        ->and($this->ticket->fresh()->ai_suggestion['draft'])->toContain('Conectar mi WhatsApp')
        ->and($this->ticket->fresh()->ai_suggested_at)->not->toBeNull();

    // What reaches the model: the report fenced as data, and the article it may lean on.
    SupportTicketReviewer::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '<reporte>')
        && str_contains($prompt->prompt, 'Mi asistente dejó de responder')
        && str_contains($prompt->prompt, '[slug: whatsapp-se-desconecto]'));
});

test('the model is never asked when the admin switched it off', function (): void {
    config()->set('atendia.support.ai_suggestions', 0);
    SupportTicketReviewer::fake()->preventStrayPrompts();

    expect(app(SuggestForSupportTicket::class)->handle($this->ticket))->toBeNull()
        ->and($this->ticket->fresh()->ai_suggestion)->toBeNull();
});

test('an idea runs no clock and gets no call', function (): void {
    SupportTicketReviewer::fake()->preventStrayPrompts();

    $idea = SupportTicket::factory()->create(['business_id' => $this->business->id, 'kind' => 'idea', 'body' => 'Estaría bueno poder agendar turnos por voz.']);

    expect(app(SuggestForSupportTicket::class)->handle($idea))->toBeNull();
});

test('a report that was already read is not paid for twice', function (): void {
    SupportTicketReviewer::fake([reading(), reading(['summary' => 'Otra'])]);

    app(SuggestForSupportTicket::class)->handle($this->ticket);
    app(SuggestForSupportTicket::class)->handle($this->ticket->fresh());

    SupportTicketReviewer::assertPrompted(fn (): bool => true);
    expect($this->ticket->fresh()->ai_suggestion['summary'])->toBe('El WhatsApp se desconectó y el asistente no responde.');
});

test('a draft that leans on an article nobody offered is thrown away', function (): void {
    SupportTicketReviewer::fake([reading(['used' => ['un-articulo-inventado']])]);

    $suggestion = app(SuggestForSupportTicket::class)->handle($this->ticket);

    expect($suggestion['draft'])->toBe('')
        ->and($suggestion['used'])->toBe([])
        ->and($suggestion['confidence'])->toBe('low');
});

test('with no related article there is no draft, however sure the model sounds', function (): void {
    $unrelated = SupportTicket::factory()->create([
        'business_id' => $this->business->id,
        'screen' => null,
        'body' => 'Xyzzy plugh qwerty zzz.',
    ]);

    SupportTicketReviewer::fake([reading(['draft' => 'Haz esto y aquello.', 'used' => ['whatsapp-se-desconecto'], 'confidence' => 'high'])]);

    $suggestion = app(SuggestForSupportTicket::class)->handle($unrelated);

    expect($suggestion['draft'])->toBe('')
        ->and($suggestion['confidence'])->toBe('low');
});

test('values the model invents fall back to what the report already says', function (): void {
    SupportTicketReviewer::fake([reading(['kind' => 'dragon', 'priority' => 'urgentisima', 'confidence' => 'seguro'])]);

    $suggestion = app(SuggestForSupportTicket::class)->handle($this->ticket);

    expect($suggestion['kind'])->toBe('problem')
        ->and($suggestion['priority'])->toBe($this->ticket->priority->value)
        // An unknown confidence is read as the cautious one.
        ->and($suggestion['confidence'])->toBe('low');
});

test('a provider that is down costs the report nothing', function (): void {
    SupportTicketReviewer::fake(fn () => throw new RuntimeException('provider down'));

    expect(app(SuggestForSupportTicket::class)->handle($this->ticket))->toBeNull()
        ->and($this->ticket->fresh()->ai_suggested_at)->toBeNull();
});

test('a report that says much the same as a recent one points at it', function (): void {
    $earlier = SupportTicket::factory()->create([
        'business_id' => $this->business->id,
        'body' => 'El asistente dejó de responder, la barra dice WhatsApp sin conectar desde el lunes.',
        'created_at' => now()->subDays(2),
    ]);

    SupportTicketReviewer::fake([reading()]);

    expect(app(SuggestForSupportTicket::class)->handle($this->ticket)['looks_like'])->toBe($earlier->code);
});

test('a different report, or an old one, is not called a look-alike', function (): void {
    SupportTicket::factory()->create(['business_id' => $this->business->id, 'body' => 'No puedo subir la foto del producto al catálogo nuevo.']);
    SupportTicket::factory()->create([
        'business_id' => $this->business->id,
        'body' => 'El asistente dejó de responder, la barra dice WhatsApp sin conectar desde el lunes.',
        'created_at' => now()->subDays(40),
    ]);

    SupportTicketReviewer::fake([reading()]);

    expect(app(SuggestForSupportTicket::class)->handle($this->ticket)['looks_like'])->toBeNull();
});

function supportAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

test('the open report shows the reading, and "use this draft" pastes it into the answer box', function (): void {
    SupportTicketReviewer::fake([reading()]);
    app(SuggestForSupportTicket::class)->handle($this->ticket);

    supportAdmin();

    Livewire::test('admin.support.index')
        ->set('open', $this->ticket->id)
        ->assertSeeHtml('data-testid="ai-suggestion"')
        ->assertSee('Sugerencia de la IA')
        ->assertSee('Respaldada por la ayuda')
        ->assertSee('Cómo conecto')
        ->assertSee('no le llega al negocio hasta que la envíes')
        ->call('useDraft')
        ->assertSet('reply.body', reading()['draft']);
});

test('a report nobody has read shows no card', function (): void {
    supportAdmin();

    Livewire::test('admin.support.index')
        ->set('open', $this->ticket->id)
        ->assertDontSeeHtml('data-testid="ai-suggestion"');
});

test('a reading with no draft says the help does not cover it and offers nothing to paste', function (): void {
    SupportTicketReviewer::fake([reading(['draft' => '', 'used' => [], 'confidence' => 'low', 'reason' => 'Pide un reembolso: lo decide una persona.'])]);
    app(SuggestForSupportTicket::class)->handle($this->ticket);

    supportAdmin();

    Livewire::test('admin.support.index')
        ->set('open', $this->ticket->id)
        ->assertSee('Sin borrador: la ayuda no cubre esto.')
        ->assertSee('Pide un reembolso')
        ->assertDontSeeHtml('data-testid="ai-use-draft"');
});

test('the card names a kind or a priority only when it differs from what the report has', function (): void {
    SupportTicketReviewer::fake([reading(['kind' => 'question', 'priority' => 'low'])]);
    app(SuggestForSupportTicket::class)->handle($this->ticket);

    supportAdmin();

    Livewire::test('admin.support.index')
        ->set('open', $this->ticket->id)
        ->assertSee('Sugiere: Una pregunta · prioridad');
});

test('a closed report no longer carries the card', function (): void {
    SupportTicketReviewer::fake([reading()]);
    app(SuggestForSupportTicket::class)->handle($this->ticket);
    $this->ticket->update(['status' => SupportTicketStatus::Resolved, 'resolved_at' => now()]);

    supportAdmin();

    Livewire::test('admin.support.index')
        ->set('open', $this->ticket->id)
        ->assertDontSeeHtml('data-testid="ai-suggestion"');
});

test('the job reads the report inside its own business and never another', function (): void {
    SupportTicketReviewer::fake([reading()]);
    $other = Business::factory()->create();

    PrepareSupportSuggestion::dispatchSync($other->id, $this->ticket->id);

    // The ticket belongs to another business: the tenant fence hides it.
    SupportTicketReviewer::assertNeverPrompted();

    PrepareSupportSuggestion::dispatchSync($this->business->id, $this->ticket->id);

    expect($this->ticket->fresh()->ai_suggestion)->not->toBeNull();
});
