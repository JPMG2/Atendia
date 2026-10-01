<?php

declare(strict_types=1);

use App\Classes\Search\Highlight;
use App\Dto\SearchHitDto;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SearchRecent;
use App\Models\Service;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Search\GlobalSearch;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Global search (⌘K)
|--------------------------------------------------------------------------
| Two lanes and one list: words find the identifiers a vector smears (a code,
| a phone), meaning finds what the owner described instead of named, and the
| two are fused by POSITION because their scores are not comparable numbers.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Cache::flush();

    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
    $this->user = User::factory()->create();
    $this->user->business()->associate($this->business)->save();

    // No provider is reachable in a test: the meaning lane stays quiet unless
    // a case binds its own embedder.
    $this->mock(KnowledgeEmbedder::class, function ($mock): void {
        $mock->shouldReceive('embedOne')->andThrow(new RuntimeException('offline'));
    });
});

function search(string $term): Collection
{
    return app(GlobalSearch::class)->find($term);
}

test('it finds a service by its words', function (): void {
    $this->actingAs($this->user);
    Service::factory()->for($this->business)->create(['name' => 'Corte de pelo']);

    $groups = search('corte');

    expect($groups->get('search.groups.services'))->not->toBeNull()
        ->and($groups->get('search.groups.services')->first()->title)->toBe('Corte de pelo');
});

test('accents do not have to be typed', function (): void {
    $this->actingAs($this->user);
    Service::factory()->for($this->business)->create(['name' => 'Depilación láser']);

    expect(search('depilacion laser')->get('search.groups.services')?->first()?->title)
        ->toBe('Depilación láser');
});

test('a product is found by its code, which no vector could do', function (): void {
    $this->actingAs($this->user);
    Product::factory()->for($this->business)->create(['name' => 'Shampoo sólido', 'code' => 'AB-120']);

    $hit = search('AB-120')->get('search.groups.products')?->first();

    expect($hit?->title)->toBe('Shampoo sólido')
        ->and($hit?->subtitle)->toBe('AB-120');
});

test('a customer is found by a piece of the phone', function (): void {
    $this->actingAs($this->user);
    Customer::factory()->for($this->business)->create(['name' => 'Ana Pérez', 'phone' => '+584140001122']);

    expect(search('414000')->get('search.groups.customers')?->first()?->title)->toBe('Ana Pérez');
});

test('screens are searchable and lead somewhere', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    // The roles are born after the user here, so the factory's own assignment
    // never ran: without a role the menu filters every item away.
    $this->user->assignRole('client');
    $this->actingAs($this->user->refresh());

    $hit = search('produc')->get('search.groups.screens')?->first();

    expect($hit)->not->toBeNull()
        ->and($hit->url)->not->toBe('');
});

test('a term under two characters searches nothing at all', function (): void {
    $this->actingAs($this->user);
    Service::factory()->for($this->business)->create(['name' => 'Corte de pelo']);

    expect(search('c'))->toBeEmpty();
});

test('groups keep the order the config gives them, never the order of the hits', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->user->assignRole('client');
    $this->actingAs($this->user->refresh());

    Service::factory()->for($this->business)->create(['name' => 'Producto estrella']);
    Product::factory()->for($this->business)->create(['name' => 'Producto real']);

    $keys = search('producto')->keys()->all();
    $expected = collect($keys)->sortBy(fn (string $key): int => match ($key) {
        'search.groups.screens' => 0,
        'search.groups.services' => 1,
        'search.groups.products' => 2,
        default => 9,
    })->values()->all();

    expect($keys)->toBe($expected);
});

test('the meaning lane staying offline never takes the words lane down', function (): void {
    $this->actingAs($this->user);
    Service::factory()->for($this->business)->create(['name' => 'Perfil tiroideo']);

    // The embedder throws for every case here; a palette that dies with its
    // provider is worse than one that finds less.
    expect(search('perfil')->get('search.groups.services')?->first()?->title)->toBe('Perfil tiroideo');
});

/** A unit vector pointing at one axis, so two of them are near or far on demand. */
function vectorAt(int $axis): array
{
    return array_values(array_replace(array_fill(0, 1536, 0.0), [$axis => 1.0]));
}

function embedderReturning(array $vector): void
{
    test()->mock(KnowledgeEmbedder::class, function ($mock) use ($vector): void {
        $mock->shouldReceive('embedOne')->andReturn($vector);
    });
}

test('meaning finds the service the words never would', function (): void {
    $this->actingAs($this->user);
    embedderReturning(vectorAt(0));

    // Nothing in "para la tiroides" is a substring of this name.
    $service = Service::factory()->for($this->business)->create(['name' => 'Perfil tiroideo', 'is_active' => true]);
    $service->forceFill(['embedding' => vectorAt(0)])->saveQuietly();

    $hits = search('para la tiroides')->get('search.groups.services');

    expect($hits?->first()?->title)->toBe('Perfil tiroideo')
        ->and($hits->first()->semantic)->toBeTrue();
});

test('a row both lanes find outranks one only meaning found', function (): void {
    $this->actingAs($this->user);
    embedderReturning(vectorAt(0));

    // "Corte" matches by words AND sits next to the query vector: it is in
    // both lists, so the fusion has to lift it over the meaning-only row.
    $both = Service::factory()->for($this->business)->create(['name' => 'Corte de pelo', 'is_active' => true]);
    $both->forceFill(['embedding' => vectorAt(1)])->saveQuietly();

    $meaningOnly = Service::factory()->for($this->business)->create(['name' => 'Peinado', 'is_active' => true]);
    $meaningOnly->forceFill(['embedding' => vectorAt(0)])->saveQuietly();

    $titles = search('corte')->get('search.groups.services')->map(fn (SearchHitDto $hit): string => $hit->title);

    expect($titles->first())->toBe('Corte de pelo')
        ->and($titles)->toContain('Peinado');
});

test('the words lane owns the row it shares, so a code is still the subtitle', function (): void {
    $this->actingAs($this->user);
    embedderReturning(vectorAt(0));

    $product = Product::factory()->for($this->business)->create([
        'name' => 'Shampoo sólido', 'code' => 'AB-120', 'is_active' => true,
    ]);
    $product->forceFill(['embedding' => vectorAt(0)])->saveQuietly();

    expect(search('shampoo')->get('search.groups.products')?->first()?->subtitle)->toBe('AB-120');
});

test('one business never sees another business rows', function (): void {
    $other = Business::factory()->create(['name' => 'Otro negocio']);
    Service::factory()->for($other)->create(['name' => 'Corte de pelo']);

    $this->actingAs($this->user);
    Service::factory()->for($this->business)->create(['name' => 'Corte de barba']);

    $titles = search('corte')->get('search.groups.services')->map(fn (SearchHitDto $hit): string => $hit->title);

    expect($titles)->toContain('Corte de barba')
        ->and($titles)->not->toContain('Corte de pelo');
});

test('the palette asks nothing until it is opened', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Service::factory()->for($this->business)->create(['name' => 'Corte de pelo']);

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->assertSet('opened', false)
        ->assertDontSee('Corte de pelo')
        ->call('open')
        ->set('term', 'corte')
        ->assertSee('Corte de pelo');
});

test('an empty palette offers what was opened last, newest first', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $row = fn (string $key, string $title): array => [
        'group' => 'search.groups.services', 'icon' => 'scissors', 'title' => $title,
        'subtitle' => 'Servicio', 'url' => '/servicios', 'key' => $key,
    ];

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->call('open')
        ->call('remember', $row('a', 'Corte de pelo'))
        ->call('remember', $row('b', 'Color'))
        // Newest first. The group header is asserted by its markup, not its
        // words: Livewire's JSON escapes the accent in "último".
        ->assertSeeHtml('cmdk-group')
        ->assertSeeInOrder(['Color', 'Corte de pelo']);
});

test('a picked row climbs instead of being listed twice, and the trail is capped', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $row = fn (string $key): array => [
        'group' => 'g', 'icon' => 'scissors', 'title' => 'Corte '.$key, 'subtitle' => '', 'url' => '/x', 'key' => $key,
    ];

    $palette = Livewire::actingAs($this->user)->test('search.palette')->call('open');

    foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $key) {
        $palette->call('remember', $row($key));
    }

    $palette->call('remember', $row('b'));

    $kept = SearchRecent::query()->where('user_id', $this->user->id)
        ->orderByDesc('updated_at')->pluck('hit_key');

    expect($kept)->toHaveCount(SearchRecent::KEEP)
        ->and($kept->first())->toBe('b')
        ->and($kept)->not->toContain('a');
});

test('the trail belongs to the person, never to another business', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $other = Business::factory()->create();
    $stranger = User::factory()->create();
    $stranger->business()->associate($other)->save();

    $row = [
        'group' => 'g', 'icon' => 'scissors', 'title' => 'Ajeno', 'subtitle' => '', 'url' => '/x', 'key' => 'z',
    ];

    Livewire::actingAs($stranger)->test('search.palette')->call('open')->call('remember', $row);

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->call('open')
        ->assertDontSee('Ajeno');
});

test('the prefix asks for things to do, and each one lands where it is done', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->call('open')
        ->set('term', '>')
        ->assertSee(__('search.groups.actions'))
        ->assertSee(__('search.actions.service'))
        // The sheet opens on arrival: an action that only navigates would be
        // the dead button all over again.
        ->assertSeeHtml('nuevo=1')
        ->set('term', '>import')
        ->assertSee(__('search.actions.import'))
        ->assertDontSee(__('search.actions.invite'));
});

test('a thread is found by what was said inside it, not only by who said it', function (): void {
    $this->actingAs($this->user);

    $thread = Conversation::factory()->for($this->business)->create(['contact_name' => 'Ana Pérez']);
    ConversationMessage::factory()->for($thread)->for($this->business)->create([
        'body' => 'Quería saber si tienen turno para depilación el viernes',
    ]);

    $hit = search('depilacion el viernes')->get('search.groups.conversations')?->first();

    expect($hit?->title)->toBe('Ana Pérez')
        ->and($hit?->subtitle)->toContain('depilación');
});

test('the actions of the screen she is standing on come first', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Livewire::actingAs($this->user)
        ->test('search.palette', ['screen' => 'team'])
        ->call('open')
        ->set('term', '>')
        ->assertSeeInOrder([__('search.actions.invite'), __('search.actions.service')]);
});

test('the matched piece is marked, accents and all, and the text stays escaped', function (): void {
    $marked = (string) Highlight::mark('Depilación láser', 'depilacion');

    expect($marked)->toBe('<mark class="cmdk-mark">Depilación</mark> láser');

    // The title is a customer's name or a message body: never trusted markup.
    expect((string) Highlight::mark('<script>alert(1)</script> corte', 'corte'))
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
});

test('a row found by meaning marks the nearest word, and whispers that it is a guess', function (): void {
    // "depilaciones" is two edits from "depilación" once the accent is folded.
    $marked = (string) Highlight::markClosest('Turnos de depilación el viernes', 'depilaciones');

    expect($marked)->toContain('<mark class="cmdk-mark is-loose">depilación</mark>');

    // Nothing near enough is left alone rather than marked at random.
    expect((string) Highlight::markClosest('Turnos de depilación el viernes', 'facturacion'))
        ->not->toContain('<mark');
});

test('the owner sees her own doors in their own group, and a client never does', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = User::factory()->create();
    $owner->business()->associate($this->business)->save();
    $owner->assignRole('admin');

    Livewire::actingAs($owner->refresh())
        ->test('search.palette')
        ->call('open')
        ->set('term', '>')
        ->assertSee(__('search.groups.admin'))
        ->assertSee(__('search.admin.moderation'));

    $this->user->assignRole('client');

    Livewire::actingAs($this->user->refresh())
        ->test('search.palette')
        ->call('open')
        ->set('term', '>')
        ->assertDontSee(__('search.admin.moderation'));
});

test('a question is handed to the assistant instead of searched as a name', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->call('open')
        ->set('term', '¿cuánto vendí ayer?')
        ->assertSeeHtml('data-testid="cmdk-ask"')
        ->set('term', 'corte')
        ->assertDontSeeHtml('data-testid="cmdk-ask"');
});

test('closing clears the box but reopening offers the last search back', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Livewire::actingAs($this->user)
        ->test('search.palette')
        ->call('open')
        ->set('term', 'corte')
        ->call('close')
        ->assertSet('term', '')
        ->assertSet('opened', false)
        // A search is usually corrected, not rewritten; the field selects it.
        ->call('open')
        ->assertSet('term', 'corte');
});
