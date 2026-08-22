<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateSocialNetwork;
use App\Livewire\Forms\Catalog\SocialNetworkForm;
use App\Models\SocialNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;

// RefreshDatabase is commented out globally in tests/Pest.php, so these tests would
// otherwise hit networks left over from previous runs (unique name violations).
uses(RefreshDatabase::class);

test('mount initializes the DTO so wire:model can bind into the nested form object', function (): void {
    // The form's `data` starts null; `setup()` (run from mount) turns it
    // into a real SocialNetworkDto. Without that, a `wire:model="form.data.name"`
    // update throws "Cannot assign array to property ...SocialNetworkDto" because
    // Livewire cannot recurse into null.
    Livewire::test('catalog.social-network')
        ->assertSet('form.data.name', '')
        ->set('form.data.name', 'Instagram')
        ->assertSet('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->assertSet('form.data.url', 'https://www.instagram.com/');
});

test('the social network table hands its rows to Alpine so the search filters client-side', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    // Rows come from the embedded payload and are rendered by Alpine, so typing in
    // the search box filters without a round-trip to the server.
    $html = Livewire::test('catalog.social-network')->html();

    expect($html)->toContain('x-for="row in filtered()"')
        ->toContain('x-data="catalogMaster(')
        ->toContain($network->name);
});

test('every row carries its id, because the name is user-editable and cannot identify a row', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    // Without the id, renaming Instagram to Insta would leave the update with no
    // stable reference to the row it came from.
    $html = Livewire::test('catalog.social-network')->html();

    expect($html)->toContain(':key="row.id"');

    // Js::from escapes the payload for a JS string literal, so unwrap it before asserting.
    preg_match("/items: JSON\.parse\('(.*?)'\)/", $html, $matches);
    $payload = json_decode(json_decode('"'.$matches[1].'"'), true);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['id'])->toBe($network->id);
});

test('a row without icon or abbreviation travels as an empty string, never as null', function (): void {
    // Both columns are nullable and Alpine paints the raw value: a null would show
    // up as the literal "null" in the cell.
    SocialNetwork::factory()->create(['name' => 'Instagram', 'icon' => null, 'abbreviation' => null]);

    $rows = Livewire::test('catalog.social-network')->get('initialRows');

    expect($rows[0]['icon'])->toBe('')
        ->and($rows[0]['abbreviation'])->toBe('');
});

test('the social network payload escapes names that would break the Alpine expression', function (): void {
    SocialNetwork::factory()->create(['name' => "L'Officiel"]);

    // A raw apostrophe used to land inside a JS string literal and break the component.
    expect(Livewire::test('catalog.social-network')->html())->not->toContain("L'Officiel");
});

test('the social network editor renders its real inputs', function (): void {
    Livewire::test('catalog.social-network')
        ->assertSee('Nombre')
        ->assertSee('Abreviatura')
        ->assertSee('URL base')
        ->assertSee('Ícono')
        ->assertSee('Estado');
});

test('the icon combobox offers the glyphs registered in config/icons.php', function (): void {
    // Free text does not work here: <x-icon> with an unknown name renders nothing
    // and the network silently ends up with no icon.
    $options = Livewire::test('catalog.social-network')->instance()->iconOptions;

    expect(collect($options)->pluck('value')->all())
        ->toEqualCanonicalizing(array_keys(config('icons')));
});

test('the social network editor seeds itself with sensible defaults', function (): void {
    // Defaults live in the SocialNetworkDto / Alpine state: a new row starts active
    // with no icon picked yet.
    $component = Livewire::test('catalog.social-network');

    // El default del alta viaja en la config `blank` del riel compartido.
    expect(railConfig($component->html(), 'blank')['active'])->toBeTrue();

    expect($component->get('form.data')->is_active)->toBeTrue()
        ->and($component->get('form.data')->icon)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| storeSocialNetwork — validation of every persisted attribute
|--------------------------------------------------------------------------
| The form saves the VALIDATED payload, so an attribute without a rule would
| be silently dropped, and an attribute with a wrong rule blocks real data.
*/

test('a social network is created with its url, icon and abbreviation', function (): void {
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->set('form.data.icon', 'instagram')
        ->set('form.data.abbreviation', 'IG')
        ->call('create')
        ->assertHasNoErrors();

    $network = SocialNetwork::where('name', 'Instagram')->first();

    expect($network->url)->toBe('https://www.instagram.com/')
        ->and($network->icon)->toBe('instagram')
        ->and($network->abbreviation)->toBe('IG');
});

test('a social network without icon or abbreviation is accepted, because both columns are nullable', function (): void {
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Threads')
        ->set('form.data.url', 'https://www.threads.net/')
        ->set('form.data.icon', '')
        ->set('form.data.abbreviation', '')
        ->call('create')
        ->assertHasNoErrors();

    $network = SocialNetwork::where('name', 'Threads')->first();

    // Empty, not '': `whereNull` would never find a network stored with an empty string.
    expect($network->icon)->toBeNull()
        ->and($network->abbreviation)->toBeNull();
});

test('a social network with no url is rejected as a field error', function (): void {
    // The column is NOT NULL: without the rule this would blow up inside tryAction
    // and surface as a vague toast instead of an error on the field.
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->call('create')
        ->assertHasErrors('url');

    expect(SocialNetwork::query()->count())->toBe(0);
});

test('something that is not a url is rejected', function (): void {
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'instagram')
        ->call('create')
        ->assertHasErrors('url');

    expect(SocialNetwork::query()->count())->toBe(0);
});

test('an icon that is not registered in config/icons.php is rejected', function (): void {
    // <x-icon name="myspace"> renders nothing, so the row would end up with an
    // invisible icon and no explanation.
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'MySpace')
        ->set('form.data.url', 'https://myspace.com/')
        ->set('form.data.icon', 'myspace')
        ->call('create')
        ->assertHasErrors('icon');

    expect(SocialNetwork::query()->count())->toBe(0);
});

test('an abbreviation longer than the column allows is rejected by validation, not by the database', function (): void {
    // The column is string(10) and the input caps at 10, so the rule has to cap at
    // 10 too instead of letting the generic max:255 through.
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->set('form.data.abbreviation', 'ABCDEFGHIJK')
        ->call('create')
        ->assertHasErrors('abbreviation');

    expect(SocialNetwork::query()->count())->toBe(0);
});

test('a name with markup is rejected', function (): void {
    Livewire::test('catalog.social-network')
        ->set('form.data.name', '<script>alert(1)</script>')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->call('create')
        ->assertHasErrors('name');
});

test('a duplicate name is caught as a field error, not as a database crash', function (): void {
    SocialNetwork::factory()->create(['name' => 'Instagram']);

    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->call('create')
        ->assertHasErrors('name');

    expect(SocialNetwork::query()->count())->toBe(1);
});

test('a url pasted with stray spaces is stored clean', function (): void {
    // Copying the address from a browser drags a trailing space along; the `url`
    // rule would reject it and the user would not understand why.
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', ' https://www.instagram.com/ ')
        ->call('create')
        ->assertHasNoErrors();

    expect(SocialNetwork::where('name', 'Instagram')->value('url'))->toBe('https://www.instagram.com/');
});

test('every attribute the action persists carries a validation rule', function (): void {
    // Guard: validate() returns only the keys that have rules, so an attribute
    // added to transformServiceData() without a rule would be dropped on save.
    $form = new SocialNetworkForm(
        // Bare host component: Form::__construct() needs one, nothing more.
        new class extends Component
        {
            public function render(): string
            {
                return '<div></div>';
            }
        },
        'form',
    );
    $form->setup();

    $payload = (new ReflectionMethod($form, 'transformServiceData'))->invoke($form);
    $rules = (new ReflectionMethod($form, 'getValidationRules'))->invoke($form, null);

    expect(array_keys($payload))->toEqualCanonicalizing(array_keys($rules));
});

test('creating a social network hands the refreshed rows back to Alpine', function (): void {
    // The table is rendered by Alpine from `items`, seeded once by `x-data`.
    // Livewire's morph preserves Alpine state and never re-evaluates `x-data`,
    // so without this event the new row stays invisible even after "Volver".
    SocialNetwork::factory()->create(['name' => 'Facebook']);

    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->call('create')
        ->assertDispatched(
            'catalog-rows-refreshed',
            fn (string $event, array $params): bool => collect($params['rows'])
                ->pluck('name')
                ->all() === ['Facebook', 'Instagram'],
        );
});

test('the success toast names the entity instead of saying "Registro"', function (): void {
    // NotificationService maps the TABLE to its Spanish name and gender. A master
    // whose table is missing from that map still saves fine and still shows a green
    // toast — it just calls the record "Registro", and nothing else catches it.
    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->call('create')
        ->assertDispatched('notify', type: 'success', message: 'Red social creada correctamente');
});

test('the table listens for the refreshed rows event', function (): void {
    expect(Livewire::test('catalog.social-network')->html())
        ->toContain('x-on:catalog-rows-refreshed="items = $event.detail.rows"');
});

test('a failed save keeps what the user typed instead of wiping the form', function (): void {
    // Force the action to blow up so store() returns an error DTO
    // (a validation failure would throw earlier and never reach this branch).
    $this->mock(
        CreateSocialNetwork::class,
        fn (MockInterface $mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('boom')),
    );

    Livewire::test('catalog.social-network')
        ->set('form.data.name', 'Instagram')
        ->set('form.data.url', 'https://www.instagram.com/')
        ->set('form.data.abbreviation', 'IG')
        ->call('create')
        ->assertDispatched('notify', type: 'error')
        // Nothing was saved, so the table must not be reloaded either.
        ->assertNotDispatched('catalog-rows-refreshed')
        ->assertSet('form.data.name', 'Instagram')
        ->assertSet('form.data.url', 'https://www.instagram.com/')
        ->assertSet('form.data.abbreviation', 'IG');
});

test('only the actions the view calls are reachable from the browser', function (): void {
    // Every public method of a Livewire component is callable as $wire.method()
    // from the console. Internal helpers must not be part of that surface.
    $component = Livewire::test('catalog.social-network')->instance();

    foreach (['resetForm', 'reloadTable'] as $helper) {
        expect((new ReflectionMethod($component, $helper))->isPublic())
            ->toBeFalse("{$helper}() is public and therefore callable from the browser");
    }
});

test('every visible string comes from a lang file, so the regional variants can override it', function (): void {
    // A typo in a key makes __() return the key itself, and the user reads
    // "catalog.social_network.create" on screen. This catches that.
    $html = Livewire::test('catalog.social-network')->html();

    expect($html)->not->toContain('catalog.social_network.')
        ->not->toContain('catalog.common.')
        // The copy is really rendered, not just absent.
        ->toContain(__('catalog.social_network.create'))
        ->toContain(__('catalog.social_network.empty'))
        ->toContain(__('catalog.common.save'));
});

test('the Alpine ternaries get their copy from lang too, not from hardcoded JS literals', function (): void {
    // Checked on the SOURCE, not on the output: Js::from(__('...')) renders to
    // 'Activa', byte for byte the same as the hardcoded literal, so the HTML cannot
    // tell them apart. x-text expressions are the easiest place to leave copy behind.
    // The chrome copy ("Editando", "Guardar cambios") moved to the shared
    // <x-catalog.form-shell> and is covered by CatalogComponentsTest.
    $source = file_get_contents(resource_path('views/components/catalog/⚡social-network.blade.php'));

    expect($source)->not->toContain("? 'Activa' : 'Inactiva'")
        ->toContain("Js::from(__('catalog.social_network.status.active'))");
});
