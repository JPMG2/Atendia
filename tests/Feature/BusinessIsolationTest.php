<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SocialLink;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
    ServiceCategory::class,
    Product::class,
    ProductImport::class,
    BusinessHour::class,
    KnowledgeDocument::class,
    KnowledgeChunk::class,
    KnowledgeSuggestion::class,
    Customer::class,
    Appointment::class,
    Conversation::class,
    ConversationMessage::class,
    Subscription::class,
    Payment::class,
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

test('the platform owner has no business and sees everything; so do jobs and the console', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    KnowledgeDocument::factory()->create(['business_id' => Business::factory()->create()->id]);
    KnowledgeDocument::factory()->create(['business_id' => Business::factory()->create()->id]);

    // Nobody signed in: a queue worker or a console command.
    expect(KnowledgeDocument::count())->toBe(2);

    $admin = User::factory()->create(['business_id' => null]);
    $admin->assignRole('admin');
    $this->actingAs($admin->refresh());

    expect(KnowledgeDocument::count())->toBe(2);
});

test('a client with no business yet is not the admin: every tenant model shows nothing, not even by id', function (string $model): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $row = $model::factory()->create(['business_id' => Business::factory()->create()->id]);

    // What the public sign-up leaves: the client role, a verified e-mail and no business until the onboarding.
    $client = User::factory()->create(['business_id' => null, 'email_verified_at' => now()]);
    $client->assignRole('client');
    $this->actingAs($client->refresh());

    expect($model::query()->count())->toBe(0)
        ->and($model::query()->find($row->id))->toBeNull();
})->with('tenant models');

test('a client with no business gets no customer file and no payment receipt of any business, and postgres stays closed', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $other = Business::factory()->create();

    Storage::disk('local')->put('receipts/ajeno.png', 'png');
    $payment = Payment::factory()->create(['business_id' => $other->id]);
    $payment->forceFill(['receipt_path' => 'receipts/ajeno.png'])->save();
    $message = ConversationMessage::factory()->create(['business_id' => $other->id]);

    $client = User::factory()->create(['business_id' => null, 'email_verified_at' => now()]);
    $client->assignRole('client');
    $this->actingAs($client->refresh());

    $this->get(route('my-payments.receipt', $payment))->assertNotFound();
    $this->get(route('conversations.media', [$message, 0]))->assertNotFound();

    // The database fence too: a raw select during that client's request finds nothing.
    $this->get(route('dashboard'));
    expect(DB::selectOne("select current_setting('app.current_tenant', true) as tenant")->tenant)->toBe('0')
        ->and(DB::select('select id from payments'))->toBe([]);
});

test('every tenant model hides the other business rows, even by id', function (string $model): void {
    $mine = Business::factory()->create();
    $theirs = Business::factory()->create();

    $ownRow = $model::factory()->create(['business_id' => $mine->id]);
    $foreignRow = $model::factory()->create(['business_id' => $theirs->id]);

    $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

    // Contains, not equals: some models (Subscription) are born automatically
    // with the business, so "my rows" can be more than the one created here.
    expect($model::query()->pluck('id')->all())->toContain($ownRow->id)
        ->not->toContain($foreignRow->id)
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
