<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\QuestionIntent;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\QuestionIntentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| "Mis estadísticas" in a real browser: the topics table as centrepiece,
| with its reading and the fix per topic, and the catalog gaps beside the charts.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(QuestionIntentSeeder::class);
});

test('the topics table reads the month and offers the fix per topic', function (): void {
    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);
    Service::factory()->create(['business_id' => $business->id, 'name' => 'Perfil tiroideo']);

    foreach ([
        ['¿Cuánto cuesta el perfil tiroideo?', 'price', 'assistant', null],
        ['¿Cuánto sale el hemograma?', 'price', 'assistant', null],
        ['¿Y la glucemia?', 'price', 'nobody', null],
        ['¿Abren los sábados?', 'hours', 'nobody', null],
        ['¿Hacen análisis a domicilio?', 'delivery', 'nobody', 'Análisis a domicilio'],
        ['¿Pueden venir a mi casa?', 'delivery', 'team', 'Análisis a domicilio'],
    ] as [$question, $intent, $resolvedBy, $subject]) {
        $thread = Conversation::factory()->create(['business_id' => $business->id]);
        $message = ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'body' => $question]);
        $analysis = ConversationAnalysis::query()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'first_message_id' => $message->id, 'last_message_id' => $message->id, 'sentiment' => 'neutral']);
        ConversationQuestion::query()->create([
            'business_id' => $business->id, 'conversation_id' => $thread->id, 'conversation_analysis_id' => $analysis->id,
            'conversation_message_id' => $message->id, 'question_intent_id' => QuestionIntent::query()->where('key', $intent)->value('id'),
            'question' => $question, 'subject' => $subject, 'resolved_by' => $resolvedBy,
        ]);
    }

    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    visit('/estadisticas')
        ->assertSee('Lo que te preguntan, por tema')
        ->assertSee('Agregar al catálogo')
        ->assertSee('«Análisis a domicilio»')
        ->screenshot(fullPage: true, filename: 'statistics-topics');
});
