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
        ->assertSee('Clientes recuperados')
        ->click('Precios y presupuestos')
        ->assertSee('¿Y la glucemia?')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'statistics-topics');
});

test('chart labels stay readable from a phone to a desktop', function (int $width): void {
    $business = Business::factory()->create();
    $business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);
    foreach ([1, 2, 2, 3, 5, 9] as $daysAgo) {
        $thread = Conversation::factory()->create(['business_id' => $business->id]);
        ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'created_at' => now()->subDays($daysAgo)]);
    }
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    $page = visit('/estadisticas')->resize($width, 900);

    // Text inside the scaled SVG measured 9px on a phone and 16px at 900px; the labels must not scale.
    $rendered = fn (string $selector): float => (float) $page->script("(() => { const t = document.querySelector('{$selector}'); const svg = t.closest('svg'); const scale = svg ? svg.getBoundingClientRect().width / svg.viewBox.baseVal.width : 1; return parseFloat(getComputedStyle(t).fontSize) * scale; })()");

    expect($rendered('.stats-chart-tick'))->toBe(12.0)
        ->and($rendered('.stats-chart-value'))->toBe(12.0);

    $page->script('document.querySelector(".stats-chart").scrollIntoView({block: "center"})');
    $page->screenshot(filename: 'statistics-chart-'.$width);
})->with([390, 900, 1280]);

test('the daily chart names today, shadows the month before and answers a tap', function (): void {
    $business = Business::factory()->create();
    $business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);
    foreach ([1, 1, 2, 31, 31, 31] as $daysAgo) {
        $thread = Conversation::factory()->create(['business_id' => $business->id]);
        ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $thread->id, 'created_at' => now()->subDays($daysAgo)]);
    }
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    $page = visit('/estadisticas')->resize(390, 900);

    $page->assertSee('Hoy')
        ->assertSee('Los 30 días anteriores')
        ->assertNoJavaScriptErrors();

    // Yesterday (two conversations) sat over the same day a month before (three).
    expect((int) $page->script('document.querySelectorAll(".stats-chart .stats-chart-ghost").length'))->toBe(1);

    $page->script('document.querySelector(".stats-chart").scrollIntoView({block: "center"})');
    $page->script('document.querySelectorAll(".stats-chart .stats-chart-hit")[28].click()');
    $page->wait(0.3);

    expect((string) $page->script('document.querySelector(".stats-chart .stats-chart-tip").textContent'))
        ->toBe(now()->subDay()->format('d/m').' · 2 (antes: 3)');

    $page->screenshot(filename: 'statistics-chart-tap-390');
    $page->script("document.documentElement.classList.add('dark')");
    $page->wait(0.3);
    $page->screenshot(filename: 'statistics-chart-tap-390-dark');
});
