<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Creating a document queues its indexing, which would call the embeddings API.
    Queue::fake();
});

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

test('a job can adopt a business and give it back afterwards', function (): void {
    // Outside a request there is no session, so a job that does not set the tenant
    // reads across every business.
    $business = Business::factory()->create();
    $tenant = app(Tenant::class);

    $seen = $tenant->for($business->id, fn (): ?int => $tenant->id());

    expect($seen)->toBe($business->id)
        ->and($tenant->id())->toBeNull();
});
