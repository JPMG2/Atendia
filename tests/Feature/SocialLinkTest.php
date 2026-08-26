<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Company;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the company keeps its social accounts', function (): void {
    $company = Company::factory()->create();
    $instagram = SocialNetwork::factory()->create(['name' => 'Instagram']);

    $company->socialLinks()->create([
        'social_network_id' => $instagram->id,
        'url' => 'https://instagram.com/atendia',
    ]);

    expect($company->socialLinks)->toHaveCount(1)
        ->and($company->socialLinks->first()->socialNetwork->name)->toBe('Instagram')
        ->and($company->socialLinks->first()->url)->toBe('https://instagram.com/atendia');
});

test('a business keeps its own accounts, and they never show up as the company ones', function (): void {
    // This is the whole point of the polymorphic table: one table, and each owner
    // only ever sees what is theirs.
    $company = Company::factory()->create();
    $business = Business::factory()->create();
    $network = SocialNetwork::factory()->create();

    $company->socialLinks()->create(['social_network_id' => $network->id, 'url' => 'https://x.com/atendia']);
    $business->socialLinks()->create(['social_network_id' => $network->id, 'url' => 'https://x.com/panaderia']);

    expect($company->socialLinks()->pluck('url')->all())->toBe(['https://x.com/atendia'])
        ->and($business->socialLinks()->pluck('url')->all())->toBe(['https://x.com/panaderia']);

    // The same network on two different owners is not a duplicate: the unique
    // index is per owner, not per network.
    expect(SocialLink::query()->where('social_network_id', $network->id)->count())->toBe(2);
});

test('the accounts come out in the order they are shown in the footer', function (): void {
    $company = Company::factory()->create();

    foreach ([2 => 'https://x.com/a', 0 => 'https://x.com/b', 1 => 'https://x.com/c'] as $order => $url) {
        $company->socialLinks()->create([
            'social_network_id' => SocialNetwork::factory()->create()->id,
            'url' => $url,
            'sort_order' => $order,
        ]);
    }

    expect($company->socialLinks()->pluck('url')->all())
        ->toBe(['https://x.com/b', 'https://x.com/c', 'https://x.com/a']);
});

test('the same network cannot be loaded twice for the same owner', function (): void {
    // Loading Instagram twice is a data-entry mistake, not a use case.
    $company = Company::factory()->create();
    $network = SocialNetwork::factory()->create();

    $company->socialLinks()->create(['social_network_id' => $network->id, 'url' => 'https://x.com/one']);

    expect(fn () => $company->socialLinks()->create([
        'social_network_id' => $network->id,
        'url' => 'https://x.com/two',
    ]))->toThrow(QueryException::class);
});

test('the link knows who it belongs to', function (): void {
    $business = Business::factory()->create();

    $link = SocialLink::factory()->for($business, 'linkable')->create();

    expect($link->linkable)->toBeInstanceOf(Business::class)
        ->and($link->linkable->id)->toBe($business->id);
});

test('a link copied with a trailing space is stored clean', function (): void {
    // Same lesson as SocialNetwork::normalizeUrl: a space picked up while copying
    // bounces the URL later on and the user never sees why.
    $link = SocialLink::factory()->create(['url' => '  https://instagram.com/atendia ']);

    expect($link->refresh()->url)->toBe('https://instagram.com/atendia');
});
