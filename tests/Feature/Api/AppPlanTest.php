<?php

declare(strict_types=1);

use App\Classes\Main\Client;
use App\Models\Business;
use App\Models\CatalogPhoto;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

/** One call as this person from the install the account already used; Sanctum's guard remembers the first person it saw. */
function asPlanApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** Catalog photos of one business, on a product of that same business: the factory would otherwise mint another business to own it. */
function photosFor(int $businessId, int $count): void
{
    app(Tenant::class)->for($businessId, function () use ($businessId, $count): void {
        $product = Product::factory()->create(['business_id' => $businessId]);

        CatalogPhoto::factory()->count($count)->create([
            'business_id' => $businessId,
            'photoable_type' => $product->getMorphClass(),
            'photoable_id' => $product->id,
        ]);
    });
}

/** An owner on a given plan. */
function ownerOn(string $plan): User
{
    $owner = payingUser();
    $owner->business->subscription->forceFill(['plan' => $plan])->save();

    return $owner->refresh();
}

describe('the plan screen', function (): void {
    it('lists the meters in the order and with the words of the web', function (): void {
        $owner = ownerOn('negocio');

        $data = asPlanApp($owner)->getJson('/api/v1/plan')->assertOk()->json('data');

        expect(collect($data['meters'])->pluck('key')->all())->toBe(['conversations', 'photos', 'audio', 'seats', 'pace'])
            ->and($data['meters'][0]['label'])->toBe('Conversaciones con la IA')
            ->and($data['meters'][0]['text'])->toBe('0 de 1.000')
            ->and($data['meters'][2]['text'])->toBe('0 de 200 min')
            ->and($data['meters'][4]['text'])->toBe('60 mensajes por hora')
            ->and($data['copy']['usage_title'])->toBe('Tu uso este mes')
            ->and($data['plan'])->toMatchArray(['code' => 'negocio', 'name' => 'Negocio', 'price' => '$79', 'per_month' => '/mes']);
    });

    it('draws a bar for what is spent and none for people and pace, like the web', function (): void {
        $meters = asPlanApp(ownerOn('negocio'))->getJson('/api/v1/plan')->json('data.meters');

        $byKey = collect($meters)->keyBy('key');

        expect($byKey['conversations']['percent'])->toBe(0)
            ->and($byKey['seats']['percent'])->toBeNull()
            ->and($byKey['seats']['state'])->toBeNull()
            ->and($byKey['pace']['percent'])->toBeNull();
    });

    it('leaves out audio and the Ask quota on a plan that does not include them', function (): void {
        $data = asPlanApp(ownerOn('emprende'))->getJson('/api/v1/plan')->assertOk()->json('data');

        expect(collect($data['meters'])->pluck('key')->all())->toBe(['conversations', 'photos', 'seats', 'pace'])
            ->and($data['ask'])->toBeNull();
    });

    it('serves the Ask quota with what is left and when it renews', function (): void {
        $ask = asPlanApp(ownerOn('negocio'))->getJson('/api/v1/plan')->json('data.ask');

        expect($ask)->toMatchArray([
            'title' => 'Pregúntale a '.config('app.name'),
            'left_label' => 'Te quedan',
            'left' => 100,
            'of' => 'de 100 consultas este mes',
            'used' => 0,
            'cap' => 100,
            'state' => 'ok',
        ]);
    });

    it('turns the meter to warning from 80 percent and to full at the cap, as the panel does', function (): void {
        $owner = ownerOn('negocio');
        SubscriptionPlan::query()->where('code', 'negocio')->firstOrFail()->update(['catalog_photos' => 10]);

        $photos = fn () => collect(asPlanApp($owner)->getJson('/api/v1/plan')->json('data.meters'))->firstWhere('key', 'photos');

        photosFor((int) $owner->business_id, 8);

        expect($photos())->toMatchArray(['text' => '8 de 10', 'percent' => 80, 'state' => 'warn']);

        photosFor((int) $owner->business_id, 2);

        expect($photos())->toMatchArray(['text' => '10 de 10', 'percent' => 100, 'state' => 'over']);
    });

    it('counts only its own business', function (): void {
        // Built first, before any tenant context exists, as row-level security demands.
        $other = Business::factory()->create();
        photosFor((int) $other->id, 3);
        $owner = ownerOn('negocio');

        $photos = collect(asPlanApp($owner)->getJson('/api/v1/plan')->json('data.meters'))->firstWhere('key', 'photos');

        expect($photos['used'])->toBe(0);
    });

    it('is the owner\'s: an agent of the business is refused', function (): void {
        $owner = ownerOn('negocio');
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        asPlanApp($agent->refresh())->getJson('/api/v1/plan')->assertForbidden();
    });

    it('needs a signed-in install', function (): void {
        $this->getJson('/api/v1/plan', apiDeviceHeaders())->assertUnauthorized();
    });

    it('reads the same figures the web piece does, so the two cannot disagree', function (): void {
        $owner = ownerOn('negocio');
        photosFor((int) $owner->business_id, 4);

        $api = asPlanApp($owner)->getJson('/api/v1/plan')->json('data.meters');

        expect($api)->toBe(Client::for($owner)->planUsage->meters);
    });
});

describe('the menu', function (): void {
    it('offers the owner "Plan y pagos" with "Mi plan" inside, and no agent', function (): void {
        $owner = ownerOn('negocio');
        $agent = payingUser();
        $agent->forceFill(['business_id' => $owner->business_id])->save();
        $agent->syncRoles(['agent']);

        $labels = fn (User $user): array => collect(asPlanApp($user)->getJson('/api/v1/menu')->json('data'))
            ->flatMap(fn (array $item): array => [$item['label'], ...collect($item['children'])->pluck('label')->all()])
            ->all();

        expect($labels($owner))->toContain('Plan y pagos', 'Mi plan')
            ->and($labels($agent->refresh()))->not->toContain('Plan y pagos');
    });
});
