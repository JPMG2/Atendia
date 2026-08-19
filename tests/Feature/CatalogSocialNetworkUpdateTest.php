<?php

declare(strict_types=1);

use App\Models\SocialNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening a row loads that record into the form', function (): void {
    $network = SocialNetwork::factory()->create([
        'name' => 'Instagram',
        'url' => 'https://www.instagram.com/',
        'icon' => 'instagram',
        'abbreviation' => 'IG',
    ]);

    $component = Livewire::test('catalog.social-network')->call('openEdit', $network->id);

    expect($component->get('form.socialNetworkId'))->toBe($network->id)
        ->and($component->get('form.socialNetworkData')->name)->toBe('Instagram')
        ->and($component->get('form.socialNetworkData')->url)->toBe('https://www.instagram.com/')
        ->and($component->get('form.socialNetworkData')->icon)->toBe('instagram')
        ->and($component->get('form.socialNetworkData')->abbreviation)->toBe('IG');
});

test('editing a record updates it instead of creating a second one', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Twitter']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.name', 'X (Twitter)')
        ->call('update')
        ->assertHasNoErrors()
        ->assertReturned(true);

    expect(SocialNetwork::query()->count())->toBe(1)
        ->and($network->fresh()->name)->toBe('X (Twitter)');
});

test('keeping its own name while editing does not trip the unique rule', function (): void {
    // `unique:social_networks,name` would reject the record against itself; the
    // update path has to ignore its own id.
    $network = SocialNetwork::factory()->create(['name' => 'Instagram', 'abbreviation' => 'IG']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.abbreviation', 'INSTA')
        ->call('update')
        ->assertHasNoErrors();

    expect($network->fresh()->abbreviation)->toBe('INSTA');
});

test('taking a name that already belongs to another network is rejected', function (): void {
    SocialNetwork::factory()->create(['name' => 'Facebook']);
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.name', 'Facebook')
        ->call('update')
        ->assertHasErrors('name')
        ->assertReturned(false);

    expect($network->fresh()->name)->toBe('Instagram');
});

test('deactivating a network is saved', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.is_active', false)
        ->call('update')
        ->assertHasNoErrors();

    expect($network->fresh()->is_active)->toBeFalse();
});

test('clearing the icon stores null instead of an empty string', function (): void {
    // The empty option of the combobox posts '', and the column is nullable:
    // `whereNull` would never find a network stored with an empty string.
    $network = SocialNetwork::factory()->create(['name' => 'Instagram', 'icon' => 'instagram']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.icon', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($network->fresh()->icon)->toBeNull();
});

test('starting a new network clears the record left over from an edit', function (): void {
    // openCreate() on the Alpine side only reset the client; the server kept the
    // previous record, so "Nueva red social" opened pre-filled with someone else's data.
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    $component = Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->call('openCreate');

    expect($component->get('form.socialNetworkId'))->toBeNull()
        ->and($component->get('form.socialNetworkData')->name)->toBe('')
        ->and($component->get('form.socialNetworkData')->url)->toBe('')
        ->and($component->get('form.socialNetworkData')->icon)->toBeNull()
        ->and($component->get('form.socialNetworkData')->abbreviation)->toBeNull();
});

test('a save that fails reports it back so the front keeps the user on the form', function (): void {
    Livewire::test('catalog.social-network')
        ->set('form.socialNetworkData.name', 'Instagram')
        ->set('form.socialNetworkData.url', 'https://www.instagram.com/')
        ->call('create')
        ->assertReturned(true);
});

test('opening a network that no longer exists warns instead of blowing up', function (): void {
    // findOrFail answered a raw 404 in the middle of the panel. It is not only a
    // crafted `$wire.openEdit(999999)`: another user deleting the row while this
    // list sits open on screen gets there too.
    Livewire::test('catalog.social-network')
        ->call('openEdit', 999999)
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('a failed open leaves the form untouched instead of half-loading a record', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    $component = Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->call('openEdit', 999999);

    // The network that WAS open must survive: socialNetworkId cannot end up pointing
    // at a row that does not exist, or the next save would target nothing.
    expect($component->get('form.socialNetworkId'))->toBe($network->id)
        ->and($component->get('form.socialNetworkData')->name)->toBe('Instagram');
});

test('asking to update with no record loaded warns instead of throwing a TypeError', function (): void {
    // $wire.update() is reachable from the console without opening any row. The
    // action now demands an int id, so an unguarded null would be a TypeError —
    // which is an Error, not an Exception, so tryAction would never catch it.
    Livewire::test('catalog.social-network')
        ->call('update')
        ->assertReturned(false)
        ->assertDispatched('notify', type: 'error', message: __('notifications.not_found'));
});

test('the update toast is announced in the feminine', function (): void {
    // "Red social" is feminine: the map has to say so, or the toast reads
    // "Red social actualizado correctamente".
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.name', 'Threads')
        ->call('update')
        ->assertDispatched('notify', type: 'success', message: 'Red social actualizada correctamente');
});

test('updating a network hands the refreshed rows back to Alpine', function (): void {
    $network = SocialNetwork::factory()->create(['name' => 'Instagram']);

    Livewire::test('catalog.social-network')
        ->call('openEdit', $network->id)
        ->set('form.socialNetworkData.name', 'Threads')
        ->call('update')
        ->assertDispatched(
            'social-networks-refreshed',
            fn (string $event, array $params): bool => collect($params['socialNetworks'])
                ->pluck('name')
                ->all() === ['Threads'],
        );
});
