<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('a business is soft deleted instead of wiped', function (): void {
    $business = Business::factory()->create();

    $business->delete();

    expect(Business::count())->toBe(0)
        ->and(Business::withTrashed()->count())->toBe(1)
        ->and($business->fresh()->deleted_at)->not->toBeNull();
});

test('the audit columns point back to the users who touched the record', function (): void {
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $business = Business::factory()->create([
        'created_by' => $author->id,
        'updated_by' => $editor->id,
        'deleted_by' => $editor->id,
    ]);

    expect($business->creator->is($author))->toBeTrue()
        ->and($business->updater->is($editor))->toBeTrue()
        ->and($business->deleter->is($editor))->toBeTrue();
});

test('deleting the author leaves the business standing with a null author', function (): void {
    $author = User::factory()->create();
    $business = Business::factory()->create(['created_by' => $author->id]);

    $author->forceDelete();

    expect($business->fresh()->created_by)->toBeNull()
        ->and(Business::count())->toBe(1);
});

test('changes to a business are logged under the business log name', function (): void {
    $business = Business::factory()->create(['name' => 'Panadería La Esquina']);

    $business->update(['name' => 'Panadería del Centro']);

    $logged = Activity::inLog('business')->where('subject_id', $business->id)->latest('id')->first();

    expect($logged)->not->toBeNull()
        // In v5 the changes live in `attribute_changes`, not in `properties`.
        ->and($logged->attribute_changes['attributes']['name'])->toBe('Panadería del Centro')
        ->and($logged->attribute_changes['old']['name'])->toBe('Panadería La Esquina');
});

test('saving without changing anything does not write an empty log entry', function (): void {
    $business = Business::factory()->create();
    $before = Activity::count();

    $business->save();

    expect(Activity::count())->toBe($before);
});
