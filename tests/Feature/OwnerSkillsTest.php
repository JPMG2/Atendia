<?php

declare(strict_types=1);

use App\Ai\Tools\OwnerBirthdays;
use App\Ai\Tools\OwnerConversations;
use App\Ai\Tools\OwnerPlanUsage;
use App\Ai\Tools\OwnerStatistics;
use App\Ai\Tools\PanelGuide;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function ownerToday(Business $business): string
{
    return now($business->localTimezone())->toDateString();
}

function inboundThread(Business $business, string $name, ConversationStatus $status = ConversationStatus::Open): Conversation
{
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_name' => $name, 'status' => $status]);
    ConversationMessage::factory()->for($thread)->create(['business_id' => $business->id, 'direction' => MessageDirection::In]);

    return $thread;
}

test('conversations counts only this business and links each thread', function (): void {
    $business = Business::factory()->create();
    $waiting = inboundThread($business, 'María López', ConversationStatus::Team);
    inboundThread($business, 'Juan Pérez');
    inboundThread(Business::factory()->create(), 'De otro negocio');

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => false,
    ]));

    expect($answer)->toContain('2 conversaciones con mensajes de clientes')
        ->toContain('Esperando a una persona del equipo en este momento: 1.')
        ->toContain('María López')
        ->toContain(route('conversations', ['hilo' => $waiting->id]))
        ->not->toContain('De otro negocio');
});

test('conversations says what customers actually asked and who answered', function (): void {
    $business = Business::factory()->create();
    $thread = inboundThread($business, 'María López');
    $message = $thread->messages()->first();
    $analysis = ConversationAnalysis::query()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'first_message_id' => $message->id, 'last_message_id' => $message->id, 'sentiment' => 'neutral']);
    ConversationQuestion::query()->create([
        'business_id' => $business->id, 'conversation_id' => $thread->id, 'conversation_analysis_id' => $analysis->id,
        'conversation_message_id' => $message->id, 'question' => '¿Tienen turnos el sábado?',
        'resolved_by' => QuestionResolution::Assistant, 'asked_at' => now(),
    ]);

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => false,
    ]));

    expect($answer)->toContain('"¿Tienen turnos el sábado?" · la respondió el asistente');
});

test('conversations lists only the waiting ones when asked', function (): void {
    $business = Business::factory()->create();
    inboundThread($business, 'María López', ConversationStatus::Team);
    inboundThread($business, 'Juan Pérez');

    $answer = (string) (new OwnerConversations($business))->handle(new Request([
        'from' => ownerToday($business), 'to' => ownerToday($business), 'only_waiting' => true,
    ]));

    expect($answer)->toContain('María López')->not->toContain('Juan Pérez');
});

test('an unreadable date is said back to the model, never guessed', function (): void {
    $answer = (string) (new OwnerConversations(Business::factory()->create()))->handle(new Request([
        'from' => 'hoy', 'to' => 'hoy', 'only_waiting' => false,
    ]));

    expect($answer)->toContain('Fechas inválidas');
});

test('birthdays in a window that crosses the new year, with the consent said', function (): void {
    $business = Business::factory()->create();
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Ana', 'birthday' => '1990-12-30', 'marketing_opt_in_at' => now()]);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Lucía', 'birthday' => '1985-01-02']);
    Customer::factory()->create(['business_id' => $business->id, 'name' => 'Carlos', 'birthday' => '1985-06-10']);

    $answer = (string) (new OwnerBirthdays($business))->handle(new Request(['from' => '2026-12-28', 'to' => '2027-01-03']));

    expect($answer)->toContain('cumplen años 2 clientes')
        ->toContain('Ana · miércoles 30/12/2026 · aceptó recibir mensajes')
        ->toContain('Lucía · sábado 02/01/2027 · no aceptó recibir mensajes')
        ->not->toContain('Carlos');
});

test('statistics reads the screen numbers at the plan depth', function (): void {
    $business = Business::factory()->create();
    inboundThread($business, 'María López');

    $answer = (string) (new OwnerStatistics($business))->handle(new Request(['month' => now($business->localTimezone())->format('Y-m')]));

    // A new business trials Negocio: patterns yes, trends (Premium) no.
    expect($answer)->toContain('- Conversaciones: 1 (0)')
        ->toContain(route('statistics'))
        ->not->toContain('Conversaciones por mes');
});

test('plan usage reads the meters of Mi plan', function (): void {
    $answer = (string) (new OwnerPlanUsage(Business::factory()->create()))->handle(new Request);

    expect($answer)->toContain('Plan: '.__('plan.names.negocio'))
        ->toContain('Consultas a este asistente este mes: 0 de 100.');
});

test('the panel guide lists the modules and explains one from its own screen texts', function (): void {
    $guide = new PanelGuide;

    expect((string) $guide->handle(new Request(['module' => 'all'])))->toContain(route('statistics'))
        ->and((string) $guide->handle(new Request(['module' => 'statistics'])))
        ->toContain(__('statistics.kpis.resolution'))
        ->toContain(__('ask.guide.statistics.resolution'));
});

test('the panel guide keeps the base texts under a partial regional file', function (): void {
    app()->setLocale('es_AR');

    expect((string) (new PanelGuide)->handle(new Request(['module' => 'my-products'])))
        ->toContain('field_price: '.__('client.products.field_price'));
});
