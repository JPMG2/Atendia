<?php

declare(strict_types=1);

use App\Mail\AccountPasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('asking for a reset answers the same for a known and an unknown address', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nadie-'.fake()->unique()->numerify('####').'@example.test']);

    $known->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.requested'));
    $unknown->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.requested'));

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));
});

test('only an address with an account actually gets the link', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => 'nadie@example.test']);
    Mail::assertNothingQueued();

    $this->post('/forgot-password', ['email' => $user->email]);
    Mail::assertQueued(AccountPasswordReset::class, 1);
});

test('asking twice in a row looks the same as the first time', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    $second = $this->post('/forgot-password', ['email' => $user->email]);

    // The broker throttles per account; saying so would confirm the account exists.
    $second->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.requested'));
});
