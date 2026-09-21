<?php

declare(strict_types=1);

use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Jobs\IndexKnowledgeDocument;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

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
        ->assertSee('Carla')
        ->assertDontSee('Marcos');
});

test('the search paints what matched', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla Pérez', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('search', 'perez')
        ->assertSeeHtml('match-hit');
});

test('the search also reaches the last message of each thread', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Reservamos la eco doppler.');
    threadFor($user, 'Marcos', '5493333333333', 'Precios.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('search', 'doppler')
        ->assertSee('Carla')
        ->assertDontSee('Marcos');
});

test('the date filter narrows the list to the picked range', function (): void {
    $user = conversationsClient();
    $old = threadFor($user, 'Antigua', '5495555555555', 'Enero.');
    $old->forceFill(['last_message_at' => now()->subDays(20)])->save();
    threadFor($user, 'Carla', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('dates', now()->subDays(2)->toDateString().'..'.now()->toDateString())
        ->assertSee('Carla')
        ->assertDontSee('Antigua');
});

test('a single picked day filters that day alone', function (): void {
    $user = conversationsClient();
    $old = threadFor($user, 'Antigua', '5495555555555', 'Enero.');
    $old->forceFill(['last_message_at' => now()->subDays(20)])->save();
    threadFor($user, 'Carla', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('dates', now()->subDays(20)->toDateString())
        ->assertSee('Antigua')
        ->assertDontSee('Carla');
});

test('a hand-crafted date payload filters nothing instead of blowing up', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('dates', 'garbage..worse')
        ->assertSee('Carla');
});

test('with filters on and nothing left the list says so', function (): void {
    $user = conversationsClient();
    threadFor($user, 'Carla', '5491111111111', 'Turnos.');
    $this->actingAs($user);

    livewire('conversations.index')
        ->set('dates', now()->subDays(30)->toDateString())
        ->assertSee(__('client.conversations.no_results'));
});

test('the inbox walks in tranches of fifteen', function (): void {
    $user = conversationsClient();

    foreach (range(1, 18) as $i) {
        Conversation::factory()->create([
            'business_id' => $user->business_id,
            'contact_name' => "Persona {$i}",
            'contact_phone' => '54911'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            'last_message_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($user);

    livewire('conversations.index')
        ->assertSee('Persona 1')
        ->assertDontSee('Persona 16')
        ->assertSee(__('pagination.load_more'))
        ->call('loadMore')
        ->assertSee('Persona 16')
        ->assertDontSee(__('pagination.load_more'));
});

test('a long thread opens on its latest tranche and can walk back', function (): void {
    $user = conversationsClient();
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    foreach (range(1, 35) as $i) {
        ConversationMessage::factory()->for($thread)->create([
            'business_id' => $user->business_id,
            'body' => "Mensaje {$i}",
        ]);
    }

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee('Mensaje 35')
        ->assertSee('Mensaje 6')
        ->assertDontSee('Mensaje 5')
        ->assertSee(__('client.conversations.older'))
        ->call('loadOlder')
        ->assertSee('Mensaje 5')
        ->assertSee('Mensaje 1')
        ->assertDontSee(__('client.conversations.older'));
});

test('the thread groups messages under day chips', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, mañana a las 9.');
    $thread->messages()->oldest('id')->first()->forceFill(['created_at' => now()->subDay()])->save();
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.day_yesterday'))
        ->assertSee(__('client.conversations.day_today'));
});

test('searching inside a thread sweeps the whole history and paints the hits', function (): void {
    $user = conversationsClient();
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id,
        'body' => 'Necesito una eco doppler.',
    ]);

    foreach (range(1, 34) as $i) {
        ConversationMessage::factory()->for($thread)->create([
            'business_id' => $user->business_id,
            'body' => "Relleno {$i}",
        ]);
    }

    $this->actingAs($user);

    // The quote lives OUTSIDE the 30-message window: only a full-history
    // sweep can find it. The walk-back button yields to the search.
    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertDontSee('eco doppler')
        ->set('threadSearch', 'doppler')
        ->assertSee('eco')
        ->assertSeeHtml('match-hit')
        // "Relleno 34" stays as the LIST row's preview; 33 lives only in the
        // canvas, so its absence proves the filter.
        ->assertDontSee('Relleno 33')
        ->assertDontSee(__('client.conversations.older'))
        ->set('threadSearch', 'zzzz')
        ->assertSee(__('client.conversations.thread_no_results'));
});

function customerThread(User $user, string $phone = '5491111111111'): array
{
    $customer = Customer::factory()->create([
        'business_id' => $user->business_id,
        'phone' => $phone,
        'profile_name' => 'Carli',
    ]);
    $thread = threadFor($user, 'Carla', $phone, 'Turnos.');
    $thread->update(['customer_id' => $customer->id]);

    return [$customer, $thread];
}

test('the customer sheet opens, edits and saves the human side', function (): void {
    $user = conversationsClient();
    [$customer, $thread] = customerThread($user);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.customers.open'))
        ->call('openCustomer')
        ->assertSet('showCustomer', true)
        ->set('customerForm.name', 'María Pérez')
        ->set('customerForm.email', 'maria@example.com')
        ->set('customerForm.birthday', '1990-05-04')
        ->set('customerForm.notes', 'Prefiere turnos de mañana.')
        ->call('saveCustomer');

    $customer->refresh();

    expect($customer->name)->toBe('María Pérez')
        ->and($customer->email)->toBe('maria@example.com')
        ->and($customer->birthday?->toDateString())->toBe('1990-05-04')
        ->and($customer->notes)->toBe('Prefiere turnos de mañana.');
});

test('a malformed email never reaches the record', function (): void {
    $user = conversationsClient();
    [$customer, $thread] = customerThread($user);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openCustomer')
        ->set('customerForm.email', 'no-es-correo')
        ->call('saveCustomer')
        ->assertHasErrors(['email']);

    expect($customer->refresh()->email)->toBeNull();
});

test('merging moves the duplicate threads here and deletes its record', function (): void {
    $user = conversationsClient();
    [$customer, $thread] = customerThread($user);
    $customer->forceFill(['email' => 'maria@example.com'])->save();

    $duplicate = Customer::factory()->create([
        'business_id' => $user->business_id,
        'phone' => '5492222222222',
        'email' => 'maria@example.com',
        'notes' => 'Vieja ficha.',
    ]);
    $oldThread = threadFor($user, 'Maru', '5492222222222', 'Hola de nuevo.');
    $oldThread->update(['customer_id' => $duplicate->id]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openCustomer')
        ->assertSee(__('client.customers.merge_title'))
        ->call('mergeCustomer');

    expect(Customer::query()->count())->toBe(1)
        ->and($oldThread->refresh()->customer_id)->toBe($customer->id)
        ->and($customer->refresh()->notes)->toBe('Vieja ficha.')
        ->and($customer->conversations_count)->toBe(2);
});

test('requesting the opt-in messages the customer and stamps the ask', function (): void {
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);

    $user = conversationsClient();
    $user->business->forceFill(['whatsapp_instance' => 'demo', 'whatsapp_connected_at' => now()])->save();
    [$customer, $thread] = customerThread($user);
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openCustomer')
        ->assertSee(__('client.customers.opt_in_button'))
        ->call('requestOptIn');

    expect($customer->refresh()->marketing_opt_in_requested_at)->not->toBeNull();
    Http::assertSentCount(1);
});

test('a customer message can be taught to the assistant from the thread', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'No lo pude confirmar.');
    $question = $thread->messages()->where('direction', MessageDirection::In)->first();
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.teach'))
        ->call('teach', $question->id)
        ->assertSet('sheetOpen', true)
        ->assertSet('form.question', $question->body)
        ->set('form.question', '¿Hacen envíos a domicilio?')
        ->set('form.answer', 'Sí, a todo el país.')
        ->call('saveFaq')
        ->assertSet('sheetOpen', false);

    $faq = KnowledgeDocument::query()->where('source_type', 'faq')->sole();

    expect($faq->title)->toBe('¿Hacen envíos a domicilio?')
        ->and($faq->business_id)->toBe($user->business_id);

    Queue::assertPushed(IndexKnowledgeDocument::class);
});

test('only the customer\'s own words open the teach sheet', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Respuesta del asistente.');
    $reply = $thread->messages()->where('direction', MessageDirection::Out)->first();

    $stranger = conversationsClient();
    $foreign = threadFor($stranger, 'Ajena', '5492222222222', 'Otra cosa.');
    $foreignQuestion = $foreign->messages()->where('direction', MessageDirection::In)->first();

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('teach', $reply->id)
        ->assertSet('sheetOpen', false)
        ->call('teach', $foreignQuestion->id)
        ->assertSet('sheetOpen', false);
});

test('an assistant reply shows where its answer came from', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, mañana a las 9.');

    ConversationMessage::factory()->out()->for($thread)->create([
        'business_id' => $user->business_id,
        'author' => MessageAuthor::Assistant,
        'body' => 'Sí, tenemos stock del alternador.',
        'knowledge_sources' => [['id' => 7, 'title' => 'Inventario 2026']],
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertSee(__('client.conversations.sources_toggle'))
        ->assertSee('Inventario 2026');
});

test('a reply without provenance shows no trail', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Hola, ¿en qué te ayudo?');
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->assertDontSee(__('client.conversations.sources_toggle'));
});

test('a taught source opens its sheet from the trail for instant correction', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, hacemos envíos.');

    $faq = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'title' => '¿Hacen envíos?',
        'content' => "Pregunta: ¿Hacen envíos?\nRespuesta: Sí, a todo el país.",
    ]);

    ConversationMessage::factory()->out()->for($thread)->create([
        'business_id' => $user->business_id,
        'author' => MessageAuthor::Assistant,
        'body' => 'Sí, hacemos envíos.',
        'knowledge_sources' => [['id' => $faq->id, 'title' => $faq->title]],
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openSource', $faq->id)
        ->assertSet('sheetOpen', true)
        ->assertSet('form.editingId', $faq->id)
        ->assertSet('form.question', '¿Hacen envíos?')
        ->assertSet('form.answer', 'Sí, a todo el país.');
});

test('an automatic source jumps to the screen that manages it', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Tenemos ecografías.');
    $services = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'services',
        'title' => 'Tus servicios',
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openSource', $services->id)
        ->assertSet('sheetOpen', false)
        ->assertRedirect(route('my-services'));
});

test('another tenant\'s document never opens from the trail', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Hola.');

    $stranger = conversationsClient();
    $foreign = KnowledgeDocument::factory()->create([
        'business_id' => $stranger->business_id,
        'source_type' => 'faq',
        'title' => 'Ajena',
        'content' => "Pregunta: Ajena\nRespuesta: Ajena.",
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openSource', $foreign->id)
        ->assertSet('sheetOpen', false)
        ->assertNoRedirect();
});

test('teaching from the thread records where the answer was born', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'No lo pude confirmar.');
    $question = $thread->messages()->where('direction', MessageDirection::In)->first();
    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('teach', $question->id)
        ->set('form.question', '¿Hacen envíos a domicilio?')
        ->set('form.answer', 'Sí, a todo el país.')
        ->call('saveFaq');

    expect(KnowledgeDocument::query()->where('source_type', 'faq')->sole()->conversation_id)
        ->toBe($thread->id);
});

test('editing a taught answer keeps its original provenance', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, hacemos envíos.');

    $faq = KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'conversation_id' => $thread->id,
        'title' => '¿Hacen envíos?',
        'content' => "Pregunta: ¿Hacen envíos?\nRespuesta: Sí.",
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $thread->id)
        ->call('openSource', $faq->id)
        ->set('form.answer', 'Sí, a todo el país.')
        ->call('saveFaq');

    expect($faq->refresh()->conversation_id)->toBe($thread->id);
});

test('the thread wears a badge when an answer was born in it', function (): void {
    Queue::fake();

    $user = conversationsClient();
    $taught = threadFor($user, 'Carla', '5491111111111', 'Sí, hacemos envíos.');
    $plain = threadFor($user, 'Bruno', '5493333333333', 'Hola.');

    KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'conversation_id' => $taught->id,
        'title' => '¿Hacen envíos?',
    ]);

    $this->actingAs($user);

    livewire('conversations.index')
        ->call('open', $taught->id)
        ->assertSee(trans_choice('client.conversations.taught_badge', 1))
        ->call('open', $plain->id)
        ->assertDontSee(trans_choice('client.conversations.taught_badge', 1));
});

test('a thread link in the URL opens that conversation straight away', function (): void {
    $user = conversationsClient();
    $thread = threadFor($user, 'Carla', '5491111111111', 'Sí, mañana a las 9.');
    $this->actingAs($user);

    Livewire::withQueryParams(['hilo' => $thread->id])
        ->test('conversations.index')
        ->assertSet('selected', $thread->id)
        ->assertSee('Sí, mañana a las 9.');
});

test('a foreign thread link opens nothing', function (): void {
    $user = conversationsClient();
    $stranger = conversationsClient();
    $foreign = threadFor($stranger, 'Ajena', '5492222222222', 'Otra cosa.');

    $this->actingAs($user);
    threadFor($user, 'Carla', '5491111111111', 'Hola.');

    Livewire::withQueryParams(['hilo' => $foreign->id])
        ->test('conversations.index')
        ->assertDontSee('Ajena')
        ->assertDontSee('Otra cosa.');
});
