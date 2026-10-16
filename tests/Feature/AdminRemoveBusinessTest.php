<?php

declare(strict_types=1);

use App\Actions\Admin\FindOrphanInstances;
use App\Actions\Admin\RaisePlatformEvent;
use App\Actions\Admin\ReleaseBridgeInstance;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformSensorStatus;
use App\Models\Business;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Services\PlatformSensors;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Removing a business — frees the bridge instance and the phone
|--------------------------------------------------------------------------
| A soft delete that also gives back what the business was holding. The
| bridge goes first: if it fails, nothing local changes.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');

    $this->seed(RolesAndPermissionsSeeder::class);
});

function removableBusiness(): Business
{
    $business = Business::factory()->create(['name' => 'Pizzería de prueba']);
    $business->forceFill([
        'whatsapp_instance' => 'business-'.$business->id,
        'whatsapp_number' => '5491100000000',
        'whatsapp_connected_at' => now(),
    ])->save();

    User::factory()->create(['business_id' => $business->id]);

    return $business->refresh();
}

function adminOf(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return tap($user->refresh(), fn (User $user) => test()->actingAs($user));
}

test('removing frees the instance and the phone, and soft-deletes the business and its people', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'SUCCESS'])]);
    $business = removableBusiness();
    $instance = $business->whatsapp_instance;
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), "/instance/logout/{$instance}"));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), "/instance/delete/{$instance}"));

    $row = Business::withTrashed()->findOrFail($business->id);

    expect($row->trashed())->toBeTrue()
        ->and($row->whatsapp_instance)->toBeNull()
        ->and($row->whatsapp_number)->toBeNull()
        ->and($row->whatsapp_connected_at)->toBeNull()
        ->and(User::query()->where('business_id', $business->id)->count())->toBe(0)
        ->and($row->subscription->canceled_at)->not->toBeNull();
});

test('an instance already gone from the bridge still lets the removal finish', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'not found'], 404)]);
    $business = removableBusiness();
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    expect(Business::withTrashed()->findOrFail($business->id)->trashed())->toBeTrue();
});

test('when the bridge fails nothing is freed and the business stays', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['error' => 'down'], 500)]);
    $business = removableBusiness();
    $instance = $business->whatsapp_instance;
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    $row = Business::query()->findOrFail($business->id);

    expect($row->whatsapp_instance)->toBe($instance)
        ->and($row->whatsapp_number)->toBe('5491100000000')
        ->and(User::query()->where('business_id', $business->id)->count())->toBe(1);
});

test('a business with no instance is removed without calling the bridge', function (): void {
    Http::fake();
    $business = Business::factory()->create();
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    Http::assertNothingSent();
    expect(Business::withTrashed()->findOrFail($business->id)->trashed())->toBeTrue();
});

test('support staff can see the list but cannot remove', function (): void {
    Http::fake();
    $business = removableBusiness();
    adminOf('support');

    Livewire::test('admin.businesses.index')->call('remove', $business->id)->assertForbidden();

    Http::assertNothingSent();
    expect(Business::query()->find($business->id))->not->toBeNull();
});

test('the open business says what removing it gives back', function (): void {
    $business = removableBusiness();
    adminOf('admin');

    expect($business->releasedOnRemoval())->toBe([
        'instance' => 'business-'.$business->id,
        'connected' => true,
        'phone' => '5491100000000',
        'people' => 1,
    ]);

    Livewire::test('admin.businesses.index')
        ->set('open', $business->id)
        ->assertSee('business-'.$business->id.' (conectada)', escape: false)
        ->assertSee('5491100000000')
        ->assertSee('el acceso de 1 persona');
});

test('the removed list holds only removed businesses and counts its own total', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'SUCCESS'])]);
    $gone = removableBusiness();
    $stays = Business::factory()->create(['name' => 'Sigue activo']);
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $gone->id);

    expect(Business::directory('', 'removed')->pluck('id')->all())->toBe([$gone->id])
        ->and(Business::directory('', '')->pluck('id')->all())->toBe([$stays->id])
        ->and(Business::directoryTotal('removed'))->toBe(1)
        ->and(Business::directoryTotal())->toBe(1)
        ->and(Business::ledger($gone->id)->situation())->toBe('removed');
});

test('restoring brings back the business and the people removed with it, not an earlier closure', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'SUCCESS'])]);
    $business = removableBusiness();
    $closedBefore = User::factory()->create(['business_id' => $business->id]);
    $closedBefore->forceFill(['deleted_at' => now()->subMonths(2)])->save();
    $inTeam = User::query()->where('business_id', $business->id)->first();
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    expect(Business::query()->find($business->id))->toBeNull();

    Livewire::test('admin.businesses.index')->call('restore', $business->id);

    $row = Business::query()->findOrFail($business->id);

    expect($row->trashed())->toBeFalse()
        ->and(User::query()->find($inTeam->id))->not->toBeNull()
        ->and(User::withTrashed()->find($closedBefore->id)->trashed())->toBeTrue()
        ->and($row->subscription->canceled_at)->toBeNull()
        // The bridge gave the instance back for good: the owner links again.
        ->and($row->whatsapp_instance)->toBeNull();
});

test('a baja she asked for before removing stays asked after restoring', function (): void {
    Http::fake();
    $business = Business::factory()->create();
    $business->subscription->forceFill(['canceled_at' => now()->subDays(5)])->save();
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);
    Livewire::test('admin.businesses.index')->call('restore', $business->id);

    expect(Business::query()->findOrFail($business->id)->subscription->canceled_at)->not->toBeNull();
});

function bridgeRuns(string ...$names): void
{
    Http::fake([
        'http://evolution.test/instance/fetchInstances*' => Http::response(array_map(fn (string $name): array => ['name' => $name], $names)),
        'http://evolution.test/*' => Http::response(['status' => 'SUCCESS']),
    ]);
}

test('only business instances nobody holds are orphans, and the platform numbers are left alone', function (): void {
    $held = Business::factory()->create();
    $held->forceFill(['whatsapp_instance' => 'business-'.$held->id])->save();
    bridgeRuns('business-'.$held->id, 'business-9001', 'atendia-demo', 'ai_project');

    $found = app(FindOrphanInstances::class)->handle();

    expect($found)->toBe(['business-9001'])
        ->and(FindOrphanInstances::lastKnown()['names'])->toBe(['business-9001']);
});

test('releasing an orphan deletes it, refuses a held one and forgets it on a removed business', function (): void {
    $held = Business::factory()->create();
    $held->forceFill(['whatsapp_instance' => 'business-'.$held->id])->save();

    $closed = Business::factory()->create();
    $closed->forceFill(['whatsapp_instance' => 'business-7001', 'whatsapp_connected_at' => now()])->save();
    $closed->delete();

    bridgeRuns('business-'.$held->id, 'business-7001', 'atendia-demo');
    adminOf('admin');

    $release = app(ReleaseBridgeInstance::class);

    expect($release->handle('business-'.$held->id))->toBeFalse()
        ->and($release->handle('atendia-demo'))->toBeFalse();

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');

    expect($release->handle('business-7001'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/instance/delete/business-7001'));

    expect(Business::withTrashed()->find($closed->id)->whatsapp_instance)->toBeNull();
});

test('support cannot release an instance or restore a business', function (): void {
    bridgeRuns('business-7002');
    $business = Business::factory()->create();
    $business->delete();
    adminOf('support');

    Livewire::test('admin.businesses.index')->call('releaseOrphan', 'business-7002')->assertForbidden();
    Livewire::test('admin.businesses.index')->call('restore', $business->id)->assertForbidden();

    expect(Business::withTrashed()->find($business->id)->trashed())->toBeTrue();
});

test('the board has a sensor that turns yellow when the bridge keeps an orphan', function (): void {
    bridgeRuns('business-7003');

    $reading = collect(app(PlatformSensors::class)->read())->firstWhere('key', 'bridge_orphans');

    expect($reading->status)->toBe(PlatformSensorStatus::Warn)
        ->and($reading->value)->toBe('1')
        ->and($reading->detail)->toContain('business-7003');
});

test('removing a business closes every line the board holds about it, and nobody else\'s', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'SUCCESS'])]);
    $business = removableBusiness();
    $other = Business::factory()->create();
    PlatformEvent::query()->delete();

    $raise = app(RaisePlatformEvent::class);
    $raise->handle(PlatformEventKind::BusinessJoined, $business->id, $business->name);
    $raise->handle(PlatformEventKind::ReceiptReceived, $business->id, null, 'receipt_received:1');
    $raise->handle(PlatformEventKind::BusinessJoined, $other->id, 'Otro');
    adminOf('admin');

    Livewire::test('admin.businesses.index')->call('remove', $business->id);

    // Her own removal is not news either: the departure the cancellation raises is closed with the rest.
    $open = PlatformEvent::query()->active()->get();

    expect($open)->toHaveCount(1)
        ->and($open->first()->about_business_id)->toBe($other->id);
});

test('a client cannot reach the admin list at all', function (): void {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->get(route('admin.businesses'))->assertForbidden();
});
