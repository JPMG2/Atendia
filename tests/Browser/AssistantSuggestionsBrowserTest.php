<?php

declare(strict_types=1);

use App\Enums\QuestionResolution;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\QuestionIntent;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\QuestionIntentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
| "Tu asistente no supo esto" in a real browser: grouped by topic, the
| team's draft on screen, and one click on "Aprobar" teaches it.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(QuestionIntentSeeder::class);
});

function browserSuggestion(Business $business, string $question, string $intentKey, int $asked, ?string $teamAnswer = null): KnowledgeSuggestion
{
    $intent = QuestionIntent::query()->where('key', $intentKey)->sole();
    $suggestion = KnowledgeSuggestion::query()->create(['business_id' => $business->id, 'question_intent_id' => $intent->id, 'question' => $question]);

    foreach (range(1, $asked) as $ignored) {
        $thread = Conversation::factory()->create(['business_id' => $business->id]);
        $analysis = ConversationAnalysis::query()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'first_message_id' => 1, 'last_message_id' => 1, 'sentiment' => 'neutral']);

        ConversationQuestion::query()->create([
            'business_id' => $business->id, 'conversation_id' => $thread->id, 'conversation_analysis_id' => $analysis->id,
            'question_intent_id' => $intent->id, 'question' => $question, 'knowledge_suggestion_id' => $suggestion->id,
            'resolved_by' => $teamAnswer !== null ? QuestionResolution::Team : QuestionResolution::Nobody, 'answer' => $teamAnswer,
        ]);
    }

    return $suggestion;
}

test('the queue shows topics and drafts, and approving teaches the answer', function (): void {
    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $saturday = browserSuggestion($business, '¿Abren los sábados?', 'hours', 3, 'Sí, los sábados abrimos de 8 a 12.');
    browserSuggestion($business, '¿Hay que ir en ayunas para el análisis de sangre?', 'hours', 1);
    $relapsed = browserSuggestion($business, '¿Aceptan tarjeta de crédito?', 'payment', 2, 'Sí, aceptamos Visa y Mastercard en una cuota.');
    $relapsed->forceFill(['knowledge_document_id' => KnowledgeDocument::factory()->create(['business_id' => $business->id, 'source_type' => 'faq'])->id])->save();
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    $page = visit('/asistente')
        ->assertNoJavaScriptErrors()
        ->assertSee('Tu asistente no supo esto')
        ->assertSee('Sí, los sábados abrimos de 8 a 12.')
        ->screenshot(fullPage: true, filename: 'assistant-suggestions');

    // The row's own button: "Aprobar las 2 de tu equipo" also reads "Aprobar".
    $page->click('button[wire\:click="approve('.$saturday->id.')"]')
        ->assertSee('Tu asistente ya sabe responder «¿Abren los sábados?».')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'assistant-suggestions-taught');

    expect(KnowledgeDocument::query()->where('title', '¿Abren los sábados?')->exists())->toBeTrue();
});
