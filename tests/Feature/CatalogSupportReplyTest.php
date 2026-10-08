<?php

declare(strict_types=1);

use App\Models\SupportReply;
use Database\Seeders\SupportReplySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the editor hands its rows to Alpine and carries the id of each', function (): void {
    $reply = SupportReply::factory()->create(['name' => 'Ya lo arreglamos']);

    $rows = Livewire::test('catalog.support-reply')->get('initialRows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($reply->id)
        ->and($rows[0]['active'])->toBeTrue();
});

test('a reply is created with its text, and the text keeps its paragraphs', function (): void {
    Livewire::test('catalog.support-reply')
        ->set('form.data.name', 'Reconectar el WhatsApp')
        ->set('form.data.body', "Entra a Conexión de WhatsApp.\n\nToca Reconectar.")
        ->call('create')
        ->assertHasNoErrors();

    $reply = SupportReply::query()->where('name', 'Reconectar el WhatsApp')->sole();

    expect($reply->body)->toBe("Entra a Conexión de WhatsApp.\n\nToca Reconectar.")
        ->and($reply->is_active)->toBeTrue();
});

test('a reply needs a body of some substance and one that fits a reply', function (): void {
    Livewire::test('catalog.support-reply')
        ->set('form.data.name', 'Corta')
        ->set('form.data.body', 'ok')
        ->call('create')
        ->assertHasErrors('body');

    Livewire::test('catalog.support-reply')
        ->set('form.data.name', 'Larga')
        ->set('form.data.body', str_repeat('a', SupportReply::BODY_MAX + 1))
        ->call('create')
        ->assertHasErrors('body');

    expect(SupportReply::query()->count())->toBe(0);
});

test('two replies cannot share a name, and editing one keeps its own', function (): void {
    SupportReply::factory()->create(['name' => 'Ya lo arreglamos']);
    $other = SupportReply::factory()->create(['name' => 'Idea recibida']);

    Livewire::test('catalog.support-reply')
        ->set('form.data.name', 'Ya lo arreglamos')
        ->set('form.data.body', 'Un texto suficientemente largo')
        ->call('create')
        ->assertHasErrors('name');

    Livewire::test('catalog.support-reply')
        ->call('openEdit', $other->id)
        ->set('form.data.body', 'Gracias por la idea, la anotamos.')
        ->call('update')
        ->assertHasNoErrors();

    expect($other->fresh()->body)->toBe('Gracias por la idea, la anotamos.');
});

test('the shelf offers only the active ones, and a switched-off one cannot be pasted', function (): void {
    $on = SupportReply::factory()->create(['name' => 'Activa']);
    $off = SupportReply::factory()->create(['name' => 'Apagada', 'is_active' => false]);

    expect(SupportReply::shelf())->toBe([$on->id => 'Activa'])
        ->and(SupportReply::bodyOf($on->id))->toBe($on->body)
        ->and(SupportReply::bodyOf($off->id))->toBeNull()
        ->and(SupportReply::bodyOf(9999))->toBeNull();
});

test('the seeder starts a shelf and never overwrites what she rewrote', function (): void {
    $this->seed(SupportReplySeeder::class);

    expect(SupportReply::query()->count())->toBe(5);

    SupportReply::query()->where('name', 'Ya lo arreglamos')->update(['body' => 'Su propio texto, ya reescrito.']);
    $this->seed(SupportReplySeeder::class);

    expect(SupportReply::query()->count())->toBe(5)
        ->and(SupportReply::query()->where('name', 'Ya lo arreglamos')->value('body'))->toBe('Su propio texto, ya reescrito.');
});
