<?php

declare(strict_types=1);

use App\Ai\Tools\SendCatalogPhotos;
use App\Dto\ModerationVerdictDto;
use App\Enums\ModerationSeverity;
use App\Models\Business;
use App\Models\CatalogPhoto;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogMatcher;
use App\Services\ContentModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
});

function photoOwner(Business $business): User
{
    return User::factory()->create(['business_id' => $business->id])->refresh();
}

test('a photo is shrunk to 1080 px, thumbnailed, and the original never stays', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->create(['business_id' => $business->id]);
    $this->actingAs(photoOwner($business));

    Livewire::test('client.catalog-photos', ['type' => 'product', 'id' => $product->id])
        ->set('form.photo', UploadedFile::fake()->image('vestido.jpg', 3000, 2000))
        ->assertHasNoErrors();

    $photo = CatalogPhoto::query()->sole();
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($photo->path));

    expect([$width, $height])->toBe([1080, 720])
        ->and($photo->path)->toStartWith("businesses/{$business->id}/catalog/")
        ->and(getimagesizefromstring(Storage::disk('public')->get($photo->thumb_path))[0])->toBe(320)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
});

test('the per-item cap and the plan cap stop new photos, the existing ones stay', function (): void {
    $business = Business::factory()->create();
    $business->subscription->update(['trial_ends_at' => now()->subDay()]); // floor plan: 3 per item
    $product = Product::factory()->create(['business_id' => $business->id]);
    CatalogPhoto::factory()->count(3)->create(['business_id' => $business->id, 'photoable_id' => $product->id]);
    $this->actingAs(photoOwner($business));

    Livewire::test('client.catalog-photos', ['type' => 'product', 'id' => $product->id])
        ->set('form.photo', UploadedFile::fake()->image('otra.jpg'))
        ->assertDispatched('notify', type: 'warning');

    expect(CatalogPhoto::query()->count())->toBe(3);
});

test('a photo moderation refuses is never stored', function (): void {
    app()->instance(ContentModeration::class, new class extends ContentModeration
    {
        public function image(string $path): ModerationVerdictDto
        {
            return new ModerationVerdictDto(ModerationSeverity::Rejected, 'sexual', 0.6);
        }
    });
    $business = Business::factory()->create();
    $product = Product::factory()->create(['business_id' => $business->id]);
    $this->actingAs(photoOwner($business));

    Livewire::test('client.catalog-photos', ['type' => 'product', 'id' => $product->id])
        ->set('form.photo', UploadedFile::fake()->image('foto.jpg'))
        ->assertHasErrors(['photo']);

    expect(CatalogPhoto::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('removing a photo drops both files', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->create(['business_id' => $business->id]);
    $this->actingAs(photoOwner($business));

    $component = Livewire::test('client.catalog-photos', ['type' => 'product', 'id' => $product->id])
        ->set('form.photo', UploadedFile::fake()->image('foto.jpg'));

    $component->call('remove', CatalogPhoto::query()->sole()->id);

    expect(CatalogPhoto::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('another business product cannot be opened', function (): void {
    $other = Product::factory()->create();
    $this->actingAs(photoOwner(Business::factory()->create()));

    Livewire::test('client.catalog-photos', ['type' => 'product', 'id' => $other->id])->assertNotFound();
});

/** The matcher needs embeddings; here it simply answers with one product. */
function matcherFinds(?Product $product): void
{
    app()->instance(CatalogMatcher::class, new class($product) extends CatalogMatcher
    {
        public function __construct(private ?Product $product) {}

        public function matches(string $item, int $limit): Collection
        {
            return collect($this->product === null ? [] : [['id' => $this->product->id, 'name' => $this->product->name, 'similarity' => 0.9, 'model' => Product::class]]);
        }
    });
}

test('the assistant sends the photos of what the customer asked for and notes it in the thread', function (): void {
    Http::fake(['http://evolution.test/*' => Http::response(['key' => ['id' => 'MSG']])]);
    $business = Business::factory()->create(['whatsapp_instance' => 'atendia-demo', 'whatsapp_connected_at' => now()]);
    $product = Product::factory()->create(['business_id' => $business->id, 'name' => 'Vestido azul']);
    Storage::disk('public')->put('a.jpg', 'jpeg-bytes');
    CatalogPhoto::factory()->count(2)->create(['business_id' => $business->id, 'photoable_id' => $product->id, 'path' => 'a.jpg']);
    $conversation = Conversation::factory()->create(['business_id' => $business->id, 'contact_phone' => '5491155550000']);
    matcherFinds($product);

    $answer = (string) (new SendCatalogPhotos($business, $conversation))->handle(new Request(['item' => 'vestido azul']));

    expect($answer)->toContain('2 foto(s) de Vestido azul')
        ->and($conversation->messages()->sole()->body)->toContain('Vestido azul');
    Http::assertSentCount(2);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/message/sendMedia/atendia-demo')
        && $request['media'] === base64_encode('jpeg-bytes'));
});

test('without photos or while suspended nothing is sent, and the model is told why', function (): void {
    Http::fake();
    $business = Business::factory()->create(['whatsapp_instance' => 'atendia-demo', 'whatsapp_connected_at' => now()]);
    $product = Product::factory()->create(['business_id' => $business->id, 'name' => 'Pollera']);
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);
    matcherFinds($product);

    expect((string) (new SendCatalogPhotos($business, $conversation))->handle(new Request(['item' => 'pollera'])))
        ->toContain('no tiene fotos');

    $business->forceFill(['suspended_at' => now()])->save();

    expect((string) (new SendCatalogPhotos($business->fresh(), $conversation))->handle(new Request(['item' => 'pollera'])))
        ->toContain('no se pueden enviar');
    Http::assertNothingSent();
});
