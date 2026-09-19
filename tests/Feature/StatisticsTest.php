<?php

declare(strict_types=1);

use App\Classes\Main\Statistics;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The clustering cache is keyed by business id, which RefreshDatabase reuses.
    Cache::flush();
    // Creating a knowledge document queues its indexing (embeddings API).
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function statsClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

/** @return list<float> A unit vector on one axis: same axis clusters, others never do. */
function axisEmbedding(int $axis): array
{
    $vector = array_fill(0, 1536, 0.0);
    $vector[$axis] = 1.0;

    return $vector;
}

function askedQuestion(User $user, string $body, int $axis): void
{
    ConversationMessage::factory()->create([
        'business_id' => $user->business_id,
        'conversation_id' => Conversation::factory()->create(['business_id' => $user->business_id])->id,
        'body' => $body,
        'embedding' => axisEmbedding($axis),
    ]);
}

test('a guest is sent to login', function (): void {
    $this->get('/estadisticas')->assertRedirect('/login');
});

test('the floor plan sees the counts and every deeper block padlocked, never hidden', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['trial_ends_at' => now()->subDay()]);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.kpis.conversations'))
        ->assertSee(__('statistics.locked_in', ['plan' => __('plan.names.negocio')]))
        ->assertSee(__('statistics.locked_in', ['plan' => __('plan.names.premium')]))
        ->assertSee(__('statistics.see_plans'));
});

test('the patterns level groups this month questions by meaning and reads the best day', function (): void {
    $user = statsClient();
    askedQuestion($user, '¿Cuánto cuesta el perfil tiroideo?', axis: 3);
    askedQuestion($user, 'Precio del perfil de tiroides, por favor', axis: 3);
    askedQuestion($user, '¿Atienden los sábados?', axis: 9);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee('¿Cuánto cuesta el perfil tiroideo?')
        ->assertSee(__('statistics.daily.title'))
        ->assertSee(__('statistics.daily.insight', ['day' => now()->translatedFormat('l j'), 'count' => 3]))
        // A lone question is noise, not a topic: it never makes the top list.
        ->assertDontSee(__('statistics.locked_in', ['plan' => __('plan.names.negocio')]));
});

test('the trends level reads the peak window and the premium gap against the catalog', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);

    askedQuestion($user, '¿Hacen blanqueamiento dental?', axis: 5);
    askedQuestion($user, 'Quiero un blanqueamiento, ¿tienen turno?', axis: 5);

    // The indexed offer lives on another meaning axis: the topic is a gap.
    $document = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'services',
    ]);
    KnowledgeChunk::factory()->create([
        'business_id' => $user->business_id,
        'knowledge_document_id' => $document->id,
        'embedding' => axisEmbedding(40),
    ]);

    $this->actingAs($user);

    livewire('statistics.index')
        ->assertSee(__('statistics.hours.title'))
        ->assertSee(__('statistics.trend.title'))
        ->assertSee(__('statistics.gaps.line', ['count' => 2, 'sample' => '¿Hacen blanqueamiento dental?']));
});

test('a topic the catalog already covers never shows up as a gap', function (): void {
    $user = statsClient();
    $user->business->subscription->update(['plan' => 'premium', 'trial_ends_at' => null]);

    askedQuestion($user, '¿Hacen limpieza dental?', axis: 5);
    askedQuestion($user, 'Turno para limpieza dental', axis: 5);

    $document = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'services',
    ]);
    KnowledgeChunk::factory()->create([
        'business_id' => $user->business_id,
        'knowledge_document_id' => $document->id,
        'embedding' => axisEmbedding(5),
    ]);

    $this->actingAs($user);

    livewire('statistics.index')->assertDontSee(__('statistics.gaps.title'));
});

test('the month kpis and the since-day-one counter add up from the threads', function (): void {
    $user = statsClient();
    askedQuestion($user, 'Hola, ¿precios?', axis: 1);
    askedQuestion($user, '¿Y los horarios?', axis: 2);

    $stats = new Statistics($user->business);

    expect($stats->monthKpis())->toMatchArray(['conversations' => 2, 'new_contacts' => 2, 'questions' => 2])
        ->and($stats->sinceDayOne['questions'])->toBe(2)
        ->and($stats->peakHours['window'])->not->toBeNull();
});
