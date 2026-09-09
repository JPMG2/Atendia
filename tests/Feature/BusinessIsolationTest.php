<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Service;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Creating a document queues its indexing, which would call the embeddings API.
    Queue::fake();
});

/**
 * Every tenant-owned model; the guardian in GoldenRulesTenancyTest derives
 * the same list from the schema, so a model missing here fails over there.
 */
dataset('tenant models', [
    Service::class,
    Product::class,
    ProductImport::class,
    BusinessHour::class,
    KnowledgeDocument::class,
    KnowledgeChunk::class,
]);

test('a business never sees another business records', function (): void {
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();

    KnowledgeDocument::factory()->create(['business_id' => $mine->id, 'title' => 'Mi manual']);
    KnowledgeDocument::factory()->create(['business_id' => $theirs->id, 'title' => 'Manual ajeno']);

    $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

    expect(KnowledgeDocument::pluck('title')->all())->toBe(['Mi manual']);
});

test('a new record gets stamped with the current business without anyone passing it', function (): void {
    // Without this the row is born ownerless: invisible to everyone, or worse,
    // visible to everyone once the column allows null.
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    $document = KnowledgeDocument::factory()->create(['business_id' => null]);

    expect($document->business_id)->toBe($business->id);
});

test('the owner has no business and therefore sees everything', function (): void {
    // business_id null is what tells the admin apart from a client, and it is
    // what turns the filter off. Queue workers and console commands land here too.
    KnowledgeDocument::factory()->create(['business_id' => Business::factory()->create()->id]);
    KnowledgeDocument::factory()->create(['business_id' => Business::factory()->create()->id]);

    $this->actingAs(User::factory()->create(['business_id' => null]));

    expect(KnowledgeDocument::count())->toBe(2);
});

test('every tenant model hides the other business rows, even by id', function (string $model): void {
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();

    $ownRow = $model::factory()->create(['business_id' => $mine->id]);
    $foreignRow = $model::factory()->create(['business_id' => $theirs->id]);

    $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

    expect($model::query()->pluck('id')->all())->toBe([$ownRow->id])
        ->and($model::query()->find($foreignRow->id))->toBeNull();
})->with('tenant models');

test('every tenant model stamps a new row with the current business', function (string $model): void {
    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id]));

    expect($model::factory()->create(['business_id' => null])->business_id)->toBe($business->id);
})->with('tenant models');

test('saving the social card never reaches another business links', function (): void {
    // social_links has no business_id (polymorphic): its isolation is that a
    // link is only ever reached THROUGH its owner. The reconcile-on-save is
    // the risky spot — a stray delete would take other tenants' rows with it.
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();
    $foreignLink = SocialLink::factory()->for($theirs, 'linkable')->create();

    $this->actingAs(User::factory()->create(['business_id' => $mine->id])->refresh());

    Livewire::test('client.section-social')
        ->call('removeSocialRow', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect(SocialLink::query()->find($foreignLink->id))->not->toBeNull();
});

test('row level security fences even raw SQL while a tenant is adopted', function (): void {
    // The Eloquent scope cannot see a DB::select; this proves Postgres can.
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();

    $ownRow = Service::factory()->create(['business_id' => $mine->id]);
    Service::factory()->create(['business_id' => $theirs->id]);

    $fenced = app(Tenant::class)->for($mine->id, fn (): array => DB::select('select id from services'));

    expect(collect($fenced)->pluck('id')->all())->toBe([$ownRow->id])
        ->and(DB::select('select id from services'))->toHaveCount(2);
});

test('a web request hands the tenant to postgres before it runs', function (): void {
    // Livewire and controllers ride this: the middleware is what makes RLS
    // hold during real traffic, not only inside an explicit Tenant::for().
    $this->seed(RolesAndPermissionsSeeder::class);

    $business = Business::factory()->create();
    $this->actingAs(User::factory()->create(['business_id' => $business->id])->refresh());

    $this->get(route('dashboard'))->assertSuccessful();

    $setting = DB::selectOne("select current_setting('app.current_tenant', true) as tenant");

    expect($setting->tenant)->toBe((string) $business->id);
});

test('a job can adopt a business and give it back afterwards', function (): void {
    // Outside a request there is no session, so a job that does not set the tenant
    // reads across every business.
    $business = Business::factory()->create();
    $tenant = app(Tenant::class);

    $seen = $tenant->for($business->id, fn (): ?int => $tenant->id());

    expect($seen)->toBe($business->id)
        ->and($tenant->id())->toBeNull();
});
