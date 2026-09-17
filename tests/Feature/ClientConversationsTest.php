<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function conversationsClient(): User
{
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    return $user;
}

function threadFor(User $user, string $name, string $phone, string $lastBody): Conversation
{
    $conversation = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => $name,
        'contact_phone' => $phone,
    ]);

    ConversationMessage::factory()->for($conversation)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);
    ConversationMessage::factory()->out()->for($conversation)->create([
        'business_id' => $user->business_id, 'body' => $lastBody,
    ]);

    return $conversation;
}

test('guests are sent to the login', function (): void {
    $this->get(route('conversations'))->assertRedirect(route('login'));
});

test('with no threads the screen explains itself', function (): void {
    $this->actingAs(conversationsClient());

    $this->get(route('conversations'))
        ->assertSuccessful()
        ->assertSee(__('client.conversations.empty_title'))
        ->assertSee(__('client.conversations.empty_body'));
});

test('the inbox lists my threads and never another tenant\'s', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Sí, mañana a las 9.');

    $stranger = conversationsClient();
    threadFor($stranger, 'Ajena', '5492222222222', 'Otra cosa.');

    $this->actingAs($user);

    $this->get(route('conversations'))
        ->assertSee('Carla')
        ->assertSee(__('client.conversations.assistant_prefix'))
        ->assertDontSee('Ajena');
});

test('opening a thread shows the whole exchange, read only', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, mañana a las 9.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee('Hola')
        ->assertSee('Sí, mañana a las 9.')
        ->assertSee($thread->contact_phone)
        ->assertSee(__('client.conversations.read_only'));
});

test('another tenant\'s thread cannot be opened even by id', function (): void {
    $stranger = conversationsClient();
    $foreign = threadFor($stranger, 'Ajena', '5492222222222', 'Privado.');

    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Lo mío.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $foreign->id)
        ->assertDontSee('Privado.')
        ->assertSee(__('client.conversations.select'));
});

test('the header shows today\'s pulse', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    $this->get(route('conversations'))->assertSee(__('client.conversations.today', ['count' => 1]));
});

test('when nothing matches literally the search falls back to meaning', function (): void {
    $user = conversationsClient();

    $eco = threadFor($user, 'Carla', '5491111111111', 'Sí, hacemos eco doppler.');
    $other = threadFor($user, 'Marcos', '5493333333333', 'Precios.');

    // Orthogonal vectors: the doppler question IS the needle, the other one
    // could not be further away — the similarity floor drops it.
    $needle = array_merge([1.0], array_fill(0, 1535, 0.0));
    $eco->messages()->first()->update(['embedding' => $needle]);
    $other->messages()->first()->update(['embedding' => array_merge([0.0, 1.0], array_fill(0, 1534, 0.0))]);

    $this->mock(KnowledgeEmbedder::class)
        ->shouldReceive('embedOne')
        ->andReturn($needle);

    $this->actingAs($user);

    livewire('conversations.index')
        ->set('search', 'ecografias')
        ->assertSee('Carla')
        ->assertDontSee('Marcos')
        ->assertSee(__('client.conversations.semantic'));
});

test('the search narrows the list by name or phone', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla Pérez', '5491111111111', 'Turnos.');
    threadFor($user, 'Marcos', '5493333333333', 'Precios.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('search', 'perez')
        ->assertSee('Carla Pérez')
        ->assertDontSee('Marcos');
});
