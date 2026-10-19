<?php

declare(strict_types=1);

use App\Actions\Admin\PurgeBusiness;
use App\Models\Business;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Erasing a removed test business — so its e-mail can sign up again
|--------------------------------------------------------------------------
| The soft delete keeps the e-mail taken. The purge is console-only, needs
| the business removed first, and never touches one that has paid.
*/

function removedBusiness(string $email = 'prueba@example.com'): Business
{
    $business = Business::factory()->create(['name' => 'Panadería Pablo', 'email' => $email]);
    $user = User::factory()->create(['business_id' => $business->id, 'email' => $email]);
    $user->delete();
    $business->delete();

    return $business;
}

test('the purge frees the e-mail and leaves no row of the business behind', function (): void {
    $business = removedBusiness();

    expect(DB::table('users')->where('email', 'prueba@example.com')->exists())->toBeTrue();

    app(PurgeBusiness::class)->handle($business);

    expect(DB::table('users')->where('email', 'prueba@example.com')->exists())->toBeFalse()
        ->and(DB::table('businesses')->where('id', $business->id)->exists())->toBeFalse();
});

test('the purge leaves the other businesses alone', function (): void {
    $other = Business::factory()->create();
    User::factory()->create(['business_id' => $other->id]);

    app(PurgeBusiness::class)->handle(removedBusiness());

    expect(DB::table('businesses')->where('id', $other->id)->exists())->toBeTrue()
        ->and(DB::table('users')->where('business_id', $other->id)->count())->toBe(1);
});

test('an active business cannot be purged', function (): void {
    $business = Business::factory()->create();

    app(PurgeBusiness::class)->handle($business);
})->throws(RuntimeException::class, 'Remove the business first');

test('a business that ever paid is never purged', function (): void {
    $business = removedBusiness();
    Payment::factory()->create(['business_id' => $business->id]);

    app(PurgeBusiness::class)->handle($business);
})->throws(RuntimeException::class, 'payments on record');

test('the command asks for the name and erases only when it matches', function (): void {
    $business = removedBusiness();

    $this->artisan('atendia:purge-business', ['id' => $business->id])
        ->expectsQuestion('This erases "Panadería Pablo" for good. Type its name to confirm', 'otra cosa')
        ->assertFailed();

    expect(DB::table('businesses')->where('id', $business->id)->exists())->toBeTrue();

    $this->artisan('atendia:purge-business', ['id' => $business->id])
        ->expectsQuestion('This erases "Panadería Pablo" for good. Type its name to confirm', 'Panadería Pablo')
        ->assertSuccessful();

    expect(DB::table('businesses')->where('id', $business->id)->exists())->toBeFalse();
});
