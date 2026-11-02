<?php

declare(strict_types=1);

use App\Actions\Account\CloseAccount;
use App\Actions\Account\RestoreAccount;
use App\Actions\Admin\RemoveBusiness;
use App\Actions\Admin\RestoreBusiness;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A closed account is erased for good once its restore window is over
|--------------------------------------------------------------------------
| Google Play requires a real deletion, not a deactivation. Only the money
| records survive; an admin removal and a suspended business are held back.
*/

beforeEach(fn () => Mail::fake());

function closedOwner(int $daysAgo): User
{
    $owner = payingUser(['email' => 'duena@example.com'], ['name' => 'Panadería Pablo', 'tax_id' => 'J-12345678-9', 'address' => 'Calle 1']);
    Customer::factory()->create(['business_id' => $owner->business_id, 'phone' => '04145550100']);

    test()->travel(-$daysAgo)->days();
    app(CloseAccount::class)->handle($owner);
    test()->travelBack();

    return $owner;
}

test('a closed account is untouched inside the restore window', function (): void {
    $owner = closedOwner(daysAgo: 29);

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    expect(DB::table('users')->where('id', $owner->id)->exists())->toBeTrue()
        ->and(DB::table('customers')->where('business_id', $owner->business_id)->count())->toBe(1);
});

test('past the window the account, the business data and the files are erased', function (): void {
    Storage::fake('public');
    $owner = closedOwner(daysAgo: 31);
    Storage::disk('public')->put("businesses/{$owner->business_id}/logo.png", 'x');

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    expect(DB::table('users')->where('email', 'duena@example.com')->exists())->toBeFalse()
        ->and(DB::table('customers')->where('business_id', $owner->business_id)->count())->toBe(0)
        ->and(DB::table('businesses')->where('id', $owner->business_id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing("businesses/{$owner->business_id}/logo.png");
});

test('the payments, their receipts and the fiscal identity survive the erase', function (): void {
    Storage::fake('local');
    $owner = closedOwner(daysAgo: 31);
    Payment::factory()->create(['business_id' => $owner->business_id]);
    Storage::disk('local')->put("businesses/{$owner->business_id}/payments/receipt.png", 'x');
    Storage::disk('local')->put("businesses/{$owner->business_id}/knowledge/menu.pdf", 'x');

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    $business = DB::table('businesses')->where('id', $owner->business_id)->first();

    expect(DB::table('payments')->where('business_id', $owner->business_id)->count())->toBe(1)
        ->and($business->name)->toBe('Panadería Pablo')
        ->and($business->tax_id)->toBe('J-12345678-9')
        ->and($business->address)->toBeNull()
        ->and($business->email)->toBeNull()
        ->and(DB::table('users')->where('email', 'duena@example.com')->exists())->toBeFalse()
        ->and(DB::table('customers')->where('business_id', $owner->business_id)->count())->toBe(0);
    Storage::disk('local')->assertExists("businesses/{$owner->business_id}/payments/receipt.png");
    Storage::disk('local')->assertMissing("businesses/{$owner->business_id}/knowledge/menu.pdf");
});

test('an account restored inside the window is never erased later', function (): void {
    $owner = closedOwner(daysAgo: 10);
    app(RestoreAccount::class)->handle(User::withTrashed()->find($owner->id));

    test()->travel(40)->days();
    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    expect(DB::table('users')->where('id', $owner->id)->whereNull('deleted_at')->exists())->toBeTrue();
});

test('an admin removal and a suspended business are held back', function (): void {
    $removed = payingUser();
    test()->travel(-40)->days();
    $removed->delete();
    $removed->business->delete();
    test()->travelBack();

    $suspended = closedOwner(daysAgo: 31);
    DB::table('businesses')->where('id', $suspended->business_id)->update(['suspended_at' => now()]);

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    expect(DB::table('users')->where('id', $removed->id)->exists())->toBeTrue()
        ->and(DB::table('users')->where('id', $suspended->id)->exists())->toBeTrue();
});

test('an owner who closed long ago, brought back and removed by the admin, is never erased', function (): void {
    $owner = closedOwner(daysAgo: 40);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $this->actingAs($admin);

    app(RestoreBusiness::class)->handle(Business::withTrashed()->find($owner->business_id));
    app(RemoveBusiness::class)->handle(Business::find($owner->business_id));

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    expect(DB::table('users')->where('id', $owner->id)->exists())->toBeTrue()
        ->and(DB::table('customers')->where('business_id', $owner->business_id)->count())->toBe(1);
});

test('the avatar, the product sheets, the audit trail and the reset tokens go too', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $owner = payingUser(['email' => 'duena@example.com']);
    Storage::disk('public')->put('avatars/'.$owner->id.'-abc.webp', 'x');
    DB::table('users')->where('id', $owner->id)->update(['avatar_path' => 'avatars/'.$owner->id.'-abc.webp']);
    Storage::disk('local')->put('imports/business-'.$owner->business_id.'/lista.xlsx', 'x');
    DB::table('password_reset_tokens')->insert(['email' => 'duena@example.com', 'token' => 'x', 'created_at' => now()]);
    $this->actingAs($owner);
    $service = Service::factory()->create(['business_id' => $owner->business_id]);
    $service->update(['name' => 'Corte con nombre']);

    test()->travel(-31)->days();
    app(CloseAccount::class)->handle($owner->fresh());
    test()->travelBack();
    auth()->logout();

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    Storage::disk('public')->assertMissing('avatars/'.$owner->id.'-abc.webp');
    Storage::disk('local')->assertMissing('imports/business-'.$owner->business_id.'/lista.xlsx');
    expect(DB::table('password_reset_tokens')->where('email', 'duena@example.com')->exists())->toBeFalse()
        ->and(DB::table('activity_log')->where('subject_type', $service->getMorphClass())->where('subject_id', $service->id)->exists())->toBeFalse();
});

test('an erased business that kept its payments can never be restored by the admin', function (): void {
    $owner = closedOwner(daysAgo: 31);
    Payment::factory()->create(['business_id' => $owner->business_id]);
    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    $this->actingAs($admin);

    $business = Business::withTrashed()->find($owner->business_id);
    app(RestoreBusiness::class)->handle($business);

    expect($business->isErased())->toBeTrue()
        ->and(Business::withTrashed()->find($owner->business_id)->trashed())->toBeTrue()
        ->and((bool) DB::table('businesses')->where('id', $owner->business_id)->value('is_active'))->toBeFalse()
        ->and($business->subscription()->first()->hasEnded())->toBeTrue();
});

test('the linked WhatsApp instance is dropped from the bridge before the erase', function (): void {
    Http::fake();
    $owner = closedOwner(daysAgo: 31);
    DB::table('businesses')->where('id', $owner->business_id)->update(['whatsapp_instance' => 'negocio-'.$owner->business_id]);

    test()->artisan('atendia:erase-closed-accounts')->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'negocio-'.$owner->business_id));
});

test('a mailed request erases a removed business that paid, keeping its payments', function (): void {
    $owner = payingUser();
    Payment::factory()->create(['business_id' => $owner->business_id]);
    $owner->delete();
    $owner->business->delete();
    $name = DB::table('businesses')->where('id', $owner->business_id)->value('name');

    test()->artisan('atendia:purge-business', ['id' => $owner->business_id, '--owner-request' => true])
        ->expectsQuestion("This erases \"{$name}\" for good. Type its name to confirm", $name)
        ->assertSuccessful();

    expect(DB::table('users')->where('id', $owner->id)->exists())->toBeFalse()
        ->and(DB::table('payments')->where('business_id', $owner->business_id)->count())->toBe(1);
});

test('the public page explains how to ask for the deletion', function (): void {
    $this->get(route('account.deletion'))
        ->assertOk()
        ->assertSee(__('deletion.heading'))
        ->assertSee(__('deletion.kept.payments'));
});
