<?php

declare(strict_types=1);

use App\Actions\Admin\PurgeBusiness;
use App\Actions\Admin\RaisePlatformEvent;
use App\Actions\Admin\ResolvePlatformEvent;
use App\Enums\PlatformEventKind;
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

test('the purge takes off the radar whoever only wrote to that business, and recounts who wrote elsewhere too', function (): void {
    $business = removedBusiness();
    $other = Business::factory()->create();

    $person = fn (string $phone, int $businesses, int $conversations): int => DB::table('platform_contacts')->insertGetId([
        'phone' => $phone, 'businesses_count' => $businesses, 'conversations_count' => $conversations, 'first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customer = fn (int $businessId, int $contactId, int $conversations) => DB::table('customers')->insert([
        'business_id' => $businessId, 'platform_contact_id' => $contactId, 'phone' => (string) $contactId, 'conversations_count' => $conversations, 'first_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $onlyHere = $person('5491100000001', 1, 1);
    $shared = $person('5491100000002', 2, 3);
    $elsewhere = $person('5491100000003', 1, 4);
    $customer($business->id, $onlyHere, 1);
    $customer($business->id, $shared, 1);
    $customer($other->id, $shared, 2);
    $customer($other->id, $elsewhere, 4);

    $erased = app(PurgeBusiness::class)->handle($business);

    expect($erased['platform_contacts'] ?? 0)->toBe(1)
        ->and(DB::table('platform_contacts')->where('id', $onlyHere)->exists())->toBeFalse()
        ->and(DB::table('platform_contacts')->where('id', $shared)->first(['businesses_count', 'conversations_count']))
        ->toEqual((object) ['businesses_count' => 1, 'conversations_count' => 2])
        ->and(DB::table('platform_contacts')->where('id', $elsewhere)->value('conversations_count'))->toBe(4);
});

test('the purge also erases the board events about the business, open or resolved, and only those', function (): void {
    $business = removedBusiness();
    $other = Business::factory()->create();
    $raise = app(RaisePlatformEvent::class);

    $raise->handle(PlatformEventKind::WhatsAppDisconnected, $business->id, 'device_removed');
    $raise->handle(PlatformEventKind::PaymentPaused, $business->id);
    app(ResolvePlatformEvent::class)->byKey('payment_paused:'.$business->id);
    $raise->handle(PlatformEventKind::WhatsAppDisconnected, $other->id);

    $erased = app(PurgeBusiness::class)->handle($business);

    expect($erased['platform_events'])->toBe(2)
        ->and(DB::table('platform_events')->where('dedupe_key', 'like', '%:'.$business->id)->count())->toBe(0)
        ->and(DB::table('platform_events')->where('about_business_id', $other->id)->count())->toBe(1);
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
