<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CurrentStatus;
use App\Models\Province;
use App\Models\Region;
use App\Models\Service;
use App\Models\SocialNetwork;
use App\Models\TaxCondition;
use App\Models\User;
use App\Traits\TracksUserActions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating stamps the logged in user as author and editor', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();

    expect($currency->created_by)->toBe($user->id)
        ->and($currency->updated_by)->toBe($user->id)
        ->and($currency->deleted_by)->toBeNull()
        ->and($currency->creator->is($user))->toBeTrue();
});

test('updating stamps the editor and leaves the original author alone', function (): void {
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($author);
    $currency = Currency::factory()->create();

    $this->actingAs($editor);
    $currency->update(['name' => 'Peso Uruguayo']);

    expect($currency->fresh()->created_by)->toBe($author->id)
        ->and($currency->fresh()->updated_by)->toBe($editor->id);
});

test('soft deleting stamps who deleted it', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();
    $currency->delete();

    $trashed = Currency::withTrashed()->find($currency->id);

    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_by)->toBe($user->id)
        ->and($trashed->deleter->is($user))->toBeTrue();
});

test('restoring clears the deleter so a live record has no one blamed for it', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();
    $currency->delete();
    $currency->restore();

    expect($currency->fresh()->deleted_by)->toBeNull()
        ->and($currency->fresh()->trashed())->toBeFalse();
});

test('a force delete does not try to stamp a row that is about to vanish', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Currency::factory()->create()->forceDelete();

    expect(Currency::withTrashed()->count())->toBe(0);
});

test('without a logged in user nothing is stamped, so seeders leave no fake author', function (): void {
    $currency = Currency::factory()->create();

    expect($currency->created_by)->toBeNull()
        ->and($currency->updated_by)->toBeNull();
});

test('an update with no session keeps the author that was already there', function (): void {
    $author = User::factory()->create();
    $this->actingAs($author);
    $currency = Currency::factory()->create();

    auth()->logout();
    $currency->update(['name' => 'Guaraní']);

    expect($currency->fresh()->updated_by)->toBe($author->id);
});

test('an author set explicitly by the caller wins over the session', function (): void {
    $session = User::factory()->create();
    $explicit = User::factory()->create();
    $this->actingAs($session);

    $currency = Currency::factory()->create(['created_by' => $explicit->id]);

    expect($currency->created_by)->toBe($explicit->id);
});

test('every master and the business itself track their author', function (string $model): void {
    expect(in_array(TracksUserActions::class, class_uses_recursive($model), true))->toBeTrue()
        ->and(in_array(SoftDeletes::class, class_uses_recursive($model), true))->toBeTrue();
})->with([
    Business::class,
    Service::class,
    BusinessSector::class,
    BusinessActivity::class,
    Country::class,
    Province::class,
    Region::class,
    Currency::class,
    TaxCondition::class,
    SocialNetwork::class,
    CurrentStatus::class,
]);
