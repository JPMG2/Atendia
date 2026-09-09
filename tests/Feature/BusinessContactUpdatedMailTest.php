<?php

declare(strict_types=1);

use App\Events\BusinessConnectionSaved;
use App\Listeners\SendContactEmailUpdated;
use App\Mail\ContactEmailUpdated;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The "still with you" mail
|--------------------------------------------------------------------------
| When the CONTACT address of a business changes, a short note lands at the
| new inbox: it proves the channel works and warns whoever did not make the
| change. Only a REPLACED address earns it — the first one is the welcome's
| territory — and both doors into the connection slice (the contact card
| and the wizard's connection step) fire the same event.
*/

beforeEach(function (): void {
    app()->setLocale('es');
});

function actingAsOwnerOf(Business $business): void
{
    test()->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());
}

test('updating the contact email through the card mails the new address', function (): void {
    Mail::fake();

    actingAsOwnerOf(Business::factory()->create(['email' => 'viejo@negocio.com']));

    Livewire::test('client.section-contact')
        ->set('form.email', 'nuevo@negocio.com')
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactEmailUpdated::class, fn (ContactEmailUpdated $mail): bool => $mail->hasTo('nuevo@negocio.com'));
});

test('the first contact email is the welcome\'s territory, not this note\'s', function (): void {
    Mail::fake();

    actingAsOwnerOf(Business::factory()->create(['email' => null]));

    Livewire::test('client.section-contact')
        ->set('form.email', 'primero@negocio.com')
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertNothingQueued();
});

test('saving the card without touching the email mails nobody', function (): void {
    Mail::fake();

    actingAsOwnerOf(Business::factory()->create(['email' => 'quieto@negocio.com']));

    Livewire::test('client.section-contact')
        ->set('form.web', 'https://negocio.com')
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertNothingQueued();
});

test('clearing the email sends nothing — there is no inbox to confirm at', function (): void {
    Mail::fake();

    actingAsOwnerOf(Business::factory()->create(['email' => 'viejo@negocio.com']));

    Livewire::test('client.section-contact')
        ->set('form.email', null)
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertNothingQueued();
});

test('the wizard connection step fires the same note when it corrects the address', function (): void {
    Mail::fake();

    actingAsOwnerOf(Business::factory()->create([
        'email' => 'viejo@negocio.com',
        'whatsapp_number' => '+58 412 5551234',
        'fallback_whatsapp_number' => '+58 412 5555678',
    ]));

    Livewire::test('business.step-whatsapp')
        ->set('form.data.email', 'corregido@negocio.com')
        ->call('finish')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactEmailUpdated::class, fn (ContactEmailUpdated $mail): bool => $mail->hasTo('corregido@negocio.com'));
});

test('the listener hangs off the event, so tomorrow\'s effects can join it', function (): void {
    Event::fake();

    Event::assertListening(BusinessConnectionSaved::class, SendContactEmailUpdated::class);
});

test('the mail names the business, shows the new address and hands out the panel', function (): void {
    $business = Business::factory()->create(['name' => 'Costuras Mary', 'email' => 'nuevo@negocio.com']);

    $mail = new ContactEmailUpdated($business);

    expect($mail->envelope()->subject)->toBe(__('mail.contact_updated.subject', ['name' => 'Costuras Mary']))
        ->and($mail->render())
        ->toContain('nuevo@negocio.com')
        ->toContain(__('mail.contact_updated.title'))
        ->toContain(route('dashboard'));
});
