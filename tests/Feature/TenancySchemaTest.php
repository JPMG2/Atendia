<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user cannot put itself inside another business through mass assignment', function (): void {
    // business_id is deliberately absent from User's Fillable. If someone adds it,
    // a registration request could carry another tenant's id and walk straight in.
    $intruder = Business::factory()->create();

    // preventSilentlyDiscardingAttributes is on, so this does not just get ignored:
    // it blows up loudly, which is what you want for a tenant boundary.
    expect(fn () => User::factory()->create()->fill(['business_id' => $intruder->id]))
        ->toThrow(MassAssignmentException::class);
});

test('the admin has no business, which is what tells it apart from a client', function (): void {
    // The isolation scope keys off this null: no business means "sees everything".
    expect(User::factory()->create()->business_id)->toBeNull();
});
