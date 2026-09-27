<?php

declare(strict_types=1);

use App\Actions\Business\SendHumanReply;
use App\Enums\ModerationSeverity;
use App\Jobs\IndexKnowledgeDocument;
use App\Mail\BusinessSuspended;
use App\Mail\ModerationAlert;
use App\Mail\ModerationAppeal;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\ModerationFlag;
use App\Models\User;
use App\Services\ContentModeration;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('atendia.admin_email', 'admin@atendia.test');
    Mail::fake();
});

/**
 * The real service over a faked moderation endpoint: TestCase binds a
 * clean fake by default, these tests prove the gate itself.
 *
 * @param  array<string, bool>  $flags
 * @param  array<string, float>  $scores
 */
function moderationAnswers(array $flags = [], array $scores = [], int $status = 200): void
{
    app()->instance(ContentModeration::class, new ContentModeration);

    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['categories' => $flags, 'category_scores' => $scores]]], $status),
        'http://evolution.test/*' => Http::response(['status' => 'PENDING']),
    ]);
}

function ownerOf(Business $business): User
{
    return User::factory()->create(['business_id' => $business->id, 'email' => 'duena@negocio.test'])->refresh();
}

test('the verdict tiers: minors and near-certain adult content are severe, the rest of adult content is refused', function (array $flags, array $scores, ModerationSeverity $expected): void {
    moderationAnswers($flags, $scores);

    expect(app(ContentModeration::class)->text('texto del negocio')->severity)->toBe($expected);
})->with([
    'minors' => [['sexual/minors' => true, 'sexual' => true], ['sexual/minors' => 0.8, 'sexual' => 0.5], ModerationSeverity::Severe],
    'adult beyond doubt' => [['sexual' => true], ['sexual' => 0.95], ModerationSeverity::Severe],
    'adult, a lingerie catalog could trip it' => [['sexual' => true], ['sexual' => 0.6], ModerationSeverity::Rejected],
    'clean' => [['sexual' => false], ['sexual' => 0.01], ModerationSeverity::Clean],
]);

test('moderation that cannot answer is unavailable, never clean', function (): void {
    moderationAnswers(status: 500);

    expect(app(ContentModeration::class)->text('texto')->severity)->toBe(ModerationSeverity::Unavailable);

    config()->set('ai.providers.openai.key', '');

    expect(app(ContentModeration::class)->text('texto')->severity)->toBe(ModerationSeverity::Unavailable);
});

test('a severe logo is refused, the business suspended and everyone told', function (): void {
    Storage::fake('public');
    moderationAnswers(['sexual/minors' => true], ['sexual/minors' => 0.9]);
    $business = Business::factory()->create(['fallback_whatsapp_number' => '+5491155550000']);
    $this->actingAs(ownerOf($business));

    Livewire::test('client.section-identity')
        ->set('form.logo_file', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasErrors(['logo_file']);

    $business->refresh();

    expect($business->logo_path)->toBeNull()
        ->and($business->isSuspended())->toBeTrue()
        ->and($business->suspension_reason)->toBe('sexual/minors')
        ->and(ModerationFlag::query()->sole())
        ->source->toBe('logo')
        ->severity->toBe(ModerationSeverity::Severe);

    Mail::assertQueued(BusinessSuspended::class, fn (BusinessSuspended $mail): bool => $mail->hasTo('duena@negocio.test'));
    Mail::assertQueued(ModerationAlert::class, fn (ModerationAlert $mail): bool => $mail->hasTo('admin@atendia.test'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'evolution.test') && str_contains((string) $request['number'], '5491155550000'));
});

test('an adult but not severe image is refused and reaches the admin without suspending', function (): void {
    Storage::fake('public');
    moderationAnswers(['sexual' => true], ['sexual' => 0.6]);
    $business = Business::factory()->create();
    $this->actingAs(ownerOf($business));

    Livewire::test('settings.section-profile')
        ->set('form.avatar_file', UploadedFile::fake()->image('yo.png'))
        ->call('save')
        ->assertHasErrors(['avatar_file']);

    expect($business->fresh()->isSuspended())->toBeFalse()
        ->and(ModerationFlag::query()->sole()->severity)->toBe(ModerationSeverity::Rejected);

    Mail::assertQueued(ModerationAlert::class);
    Mail::assertNotQueued(BusinessSuspended::class);
});

test('an image moderation could not check is refused and nothing is recorded', function (): void {
    Storage::fake('public');
    moderationAnswers(status: 503);
    $business = Business::factory()->create();
    $this->actingAs(ownerOf($business));

    Livewire::test('client.section-identity')
        ->set('form.logo_file', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasErrors(['logo_file' => __('moderation.upload.unavailable')]);

    expect(ModerationFlag::query()->count())->toBe(0)
        ->and($business->fresh()->isSuspended())->toBeFalse();
});

test('an svg logo is refused: moderation cannot see inside it', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(ownerOf($business));

    Livewire::test('client.section-identity')
        ->set('form.logo_file', UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'))
        ->call('save')
        ->assertHasErrors(['logo_file']);
});

test('a severe text the business writes suspends it through its knowledge document', function (): void {
    moderationAnswers(['sexual/minors' => true], ['sexual/minors' => 0.99]);
    // Only the indexing is held back: embeddings are not what this proves.
    Bus::fake([IndexKnowledgeDocument::class]);
    $business = Business::factory()->create();

    KnowledgeDocument::query()->create([
        'business_id' => $business->id,
        'source_type' => 'faq',
        'title' => 'Pregunta',
        'content' => 'contenido prohibido',
    ]);

    expect($business->fresh()->isSuspended())->toBeTrue()
        ->and(ModerationFlag::query()->sole())->kind->toBe('text')->source->toBe('faq');
});

test('a suspended business sends nothing to its customers', function (): void {
    moderationAnswers();
    $business = Business::factory()->create([
        'whatsapp_instance' => 'atendia-demo',
        'whatsapp_connected_at' => now(),
        'suspended_at' => now(),
    ]);
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);

    expect($business->canMessageCustomers())->toBeFalse()
        ->and(app(SendHumanReply::class)->handle($business, $conversation, 'Hola'))->toBeNull();

    Http::assertNothingSent();
});

test('the suspended business reads why on every panel screen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $business = Business::factory()->create(['suspended_at' => now()]);
    $owner = ownerOf($business);
    $owner->syncRoles(['client']);
    $this->actingAs($owner);

    $this->get(route('dashboard'))->assertOk()->assertSee(__('moderation.banner.title'));
});

test('only the admin opens the moderation desk, and lifting wakes the business', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $business = Business::factory()->create(['name' => 'Costuras Mary', 'suspended_at' => now(), 'suspension_reason' => 'sexual']);
    ModerationFlag::factory()->severe()->create(['business_id' => $business->id]);

    $client = ownerOf($business);
    $client->syncRoles(['client']);
    $this->actingAs($client)->get(route('admin.moderation'))->assertForbidden();

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin)->get(route('admin.moderation'))->assertOk()->assertSee('Costuras Mary');

    Livewire::test('admin.moderation')->call('lift', $business->id);

    expect($business->fresh()->isSuspended())->toBeFalse()
        ->and(ModerationFlag::query()->sole()->reviewed_at)->not->toBeNull();
});

test('three refused images in a week suspend even when none alone was severe', function (): void {
    Storage::fake('public');
    moderationAnswers(['sexual' => true], ['sexual' => 0.6]);
    $business = Business::factory()->create();
    ModerationFlag::factory()->count(2)->create(['business_id' => $business->id]);
    ModerationFlag::factory()->create(['business_id' => $business->id, 'created_at' => now()->subDays(8)]);
    $this->actingAs(ownerOf($business));

    Livewire::test('client.section-identity')
        ->set('form.logo_file', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect($business->fresh())
        ->isSuspended()->toBeTrue()
        ->suspension_reason->toBe('repeat');
});

test('a suspended business appeals once, and the admin hears of it', function (): void {
    $business = Business::factory()->create(['suspended_at' => now()]);
    $this->actingAs(ownerOf($business));

    Livewire::test('moderation.appeal')
        ->set('form.message', 'corto')
        ->call('send')
        ->assertHasErrors(['message']);

    Livewire::test('moderation.appeal')
        ->set('form.message', 'Vendemos trajes de baño y la foto es de nuestro catálogo.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee(__('moderation.appeal.waiting', ['date' => now()->format('d/m/Y')]));

    expect($business->fresh()->appealed_at)->not->toBeNull();
    Mail::assertQueued(ModerationAppeal::class, fn (ModerationAppeal $mail): bool => $mail->hasTo('admin@atendia.test'));

    Livewire::test('moderation.appeal')
        ->set('form.message', 'Otra apelación más, por si acaso.')
        ->call('send')
        ->assertDispatched('notify', type: 'warning');
});
