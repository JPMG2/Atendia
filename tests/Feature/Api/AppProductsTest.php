<?php

declare(strict_types=1);

use App\Enums\QuestionResolution;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationQuestion;
use App\Models\Product;
use App\Models\ServiceAttribute;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Tus productos" over the API
|--------------------------------------------------------------------------
| The phone reads the web's own list cut and writes through the web's rules and
| Actions: the words, the window, the stock switch and the checks are the web's,
| and a product of another business is never reachable. The spreadsheet import
| stays on the web.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    // Every offer change reaches the assistant's knowledge: indexing it would call OpenAI.
    Queue::fake();
});

function asProductsApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** @param  array<string, mixed>  $attributes */
function productOf(User $owner, array $attributes = []): Product
{
    return Product::factory()->create(['business_id' => $owner->business_id, ...$attributes]);
}

function goodsType(array $attributes = []): ServiceType
{
    $modality = ServiceModality::query()->where('code', 'producto')->first() ?? ServiceModality::factory()->create(['code' => 'producto']);

    return ServiceType::factory()->create(['service_modality_id' => $modality->id, ...$attributes]);
}

describe('the list', function (): void {
    it('says every row in the web\'s words: the price, the code and the stock state', function (): void {
        $owner = payingUser();
        productOf($owner, ['name' => 'Taladro', 'code' => 'TAL-01', 'price' => 185000, 'in_stock' => true]);
        productOf($owner, ['name' => 'Martillo', 'price' => null, 'in_stock' => false]);

        $data = asProductsApp($owner)->getJson('/api/v1/products')->assertOk()->json('data');
        $rows = collect($data['rows'])->keyBy('name');

        expect($rows['Taladro']['price'])->toBe('$ 185.000')
            ->and($rows['Taladro']['code'])->toBe('TAL-01')
            ->and($rows['Taladro']['inStock'])->toBeTrue()
            ->and($rows['Taladro']['outNote'])->toBeNull()
            ->and($rows['Taladro']['editLabel'])->toBe(__('client.products.edit', ['name' => 'Taladro']))
            ->and($rows['Taladro']['toggleLabel'])->toBe(__('client.products.toggle_stock', ['name' => 'Taladro']))
            ->and($rows['Taladro']['deleteTitle'])->toBe(__('client.products.delete_title', ['name' => 'Taladro']))
            ->and($rows['Martillo']['price'])->toBeNull()
            ->and($rows['Martillo']['inStock'])->toBeFalse()
            ->and($rows['Martillo']['outNote'])->toBe(__('client.products.out_note'))
            ->and($data['countLabel'])->toBe(trans_choice('client.products.count', 2, ['count' => 2]))
            ->and($data['any'])->toBeTrue()
            ->and($data['copy']['add'])->toBe(__('client.products.add'))
            ->and($data['copy']['sheet']['fields']['stock'])->toBe(__('client.products.field_stock'));
    });

    it('walks in windows of eight and says whether there is more', function (): void {
        $owner = payingUser();

        foreach (range(1, 11) as $index) {
            productOf($owner, ['name' => "Producto {$index}"]);
        }

        $first = asProductsApp($owner)->getJson('/api/v1/products')->assertOk()->json('data');
        $more = asProductsApp($owner)->getJson('/api/v1/products?visible=16')->assertOk()->json('data');

        expect($first['rows'])->toHaveCount(8)->and($first['hasMore'])->toBeTrue()->and($first['total'])->toBe(11)
            ->and($first['showing'])->toBe(__('pagination.showing', ['shown' => 8, 'total' => 11]))
            ->and($more['rows'])->toHaveCount(11)->and($more['hasMore'])->toBeFalse();
    });

    it('searches without accents over the name and the code, and filters by stock', function (): void {
        $owner = payingUser();
        productOf($owner, ['name' => 'Cañería', 'code' => 'CAN-9']);
        productOf($owner, ['name' => 'Martillo', 'in_stock' => false]);

        $names = fn (string $query): array => collect(asProductsApp($owner)->getJson('/api/v1/products?'.$query)->assertOk()->json('data.rows'))->pluck('name')->all();

        expect($names('search=caneria'))->toBe(['Cañería'])
            ->and($names('search=can-9'))->toBe(['Cañería'])
            ->and($names('filter=out'))->toBe(['Martillo'])
            ->and($names('filter=available'))->toBe(['Cañería'])
            ->and($names('search=zzz'))->toBe([]);
    });

    it('says there is nothing yet, in the web\'s words', function (): void {
        $data = asProductsApp(payingUser())->getJson('/api/v1/products')->assertOk()->json('data');

        expect($data['any'])->toBeFalse()->and($data['rows'])->toBe([])
            ->and($data['copy']['emptyTitle'])->toBe(__('client.products.empty_title'))
            ->and($data['copy']['emptyOr'])->toBe(__('client.products.empty_or'));
    });

    it('refuses a filter the web does not have', function (): void {
        asProductsApp(payingUser())->getJson('/api/v1/products?filter=vip')->assertUnprocessable()->assertJsonValidationErrors('filter');
    });

    it('never lists the products of another business', function (): void {
        $owner = payingUser();
        productOf(payingUser(), ['name' => 'Ajeno']);

        expect(asProductsApp($owner)->getJson('/api/v1/products')->assertOk()->json('data.rows'))->toBe([]);
    });

    it('is closed to an agent', function (): void {
        $owner = payingUser();
        $agent = User::factory()->create(['business_id' => $owner->business_id]);
        $agent->syncRoles(['agent']);

        asProductsApp($agent)->getJson('/api/v1/products')->assertForbidden();
        asProductsApp($agent)->postJson('/api/v1/products', ['name' => 'Taladro'])->assertForbidden();
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/products')->assertUnauthorized();
    });
});

describe('the sheet', function (): void {
    it('opens blank with the types of products to pick', function (): void {
        $owner = payingUser();
        $goods = goodsType(['name' => 'Herramienta']);
        ServiceType::factory()->create(['name' => 'Peluquería']);

        $data = asProductsApp($owner)->getJson('/api/v1/products/new')->assertOk()->json('data');

        expect($data['sheet']['title'])->toBe(__('client.products.sheet_new_title'))
            ->and($data['sheet']['id'])->toBeNull()
            ->and($data['sheet']['values']['in_stock'])->toBeTrue()
            // Only products' own types: a service's type is not offered here.
            ->and(collect($data['sheet']['types'])->pluck('id')->all())->toBe([$goods->id])
            ->and($data['copy']['sheet']['helps']['stock'])->toBe(__('client.products.stock_help'));
    });

    it('opens on a stored product with its price and quantity as they were typed and its type\'s own fields', function (): void {
        $owner = payingUser();
        $type = goodsType(['name' => 'Herramienta']);
        $watts = ServiceAttribute::factory()->create(['name' => 'Potencia', 'data_type' => 'number', 'unit' => 'W', 'is_active' => true]);
        $type->serviceAttributes()->attach($watts->id, ['is_required' => true, 'sort_order' => 0]);
        $product = productOf($owner, ['name' => 'Taladro', 'service_type_id' => $type->id, 'price' => 185000, 'stock' => 12, 'attribute_values' => [(string) $watts->id => '700']]);

        $sheet = asProductsApp($owner)->getJson("/api/v1/products/{$product->id}")->assertOk()->json('data.sheet');

        expect($sheet['title'])->toBe(__('client.products.sheet_title', ['name' => 'Taladro']))
            ->and($sheet['values']['price'])->toBe('185000')
            ->and($sheet['values']['stock'])->toBe('12')
            ->and($sheet['values']['attribute_values'])->toBe([(string) $watts->id => '700'])
            ->and($sheet['attributes'][0])->toMatchArray(['id' => $watts->id, 'data_type' => 'number', 'unit' => 'W', 'is_required' => true]);
    });

    it('grows with a picked type\'s fields, and only for the types of products', function (): void {
        $owner = payingUser();
        $type = goodsType();
        $field = ServiceAttribute::factory()->create(['data_type' => 'boolean', 'is_active' => true]);
        $type->serviceAttributes()->attach($field->id, ['is_required' => false, 'sort_order' => 0]);
        $service = ServiceType::factory()->create();

        expect(asProductsApp($owner)->getJson("/api/v1/products/types/{$type->id}")->assertOk()->json('data.attributes.0.id'))->toBe($field->id);
        asProductsApp($owner)->getJson("/api/v1/products/types/{$service->id}")->assertNotFound();
    });

    it('does not reach the product of another business', function (): void {
        $owner = payingUser();
        $foreign = productOf(payingUser(), ['name' => 'Ajeno']);

        asProductsApp($owner)->getJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        asProductsApp($owner)->putJson("/api/v1/products/{$foreign->id}", ['name' => 'Mío'])->assertNotFound();
        asProductsApp($owner)->deleteJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        asProductsApp($owner)->postJson("/api/v1/products/{$foreign->id}/toggle")->assertNotFound();
        asProductsApp($owner)->postJson("/api/v1/products/{$foreign->id}/restore")->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): string => Product::query()->whereKey($foreign->id)->value('name')))->toBe('Ajeno');
    });
});

describe('saving a product', function (): void {
    it('creates one as the web\'s sheet does and says it in the web\'s words', function (): void {
        $owner = payingUser();

        $response = asProductsApp($owner)->postJson('/api/v1/products', [
            'name' => '  Taladro   percutor ', 'code' => 'TAL-01', 'price' => '185000', 'stock' => '12', 'description' => 'Con maletín.', 'in_stock' => true,
        ])->assertCreated();

        $product = $owner->business->products()->sole();

        expect($product->name)->toBe('Taladro percutor')
            ->and($product->code)->toBe('TAL-01')
            ->and((float) $product->price)->toBe(185000.0)
            ->and((float) $product->stock)->toBe(12.0)
            ->and($product->in_stock)->toBeTrue()
            ->and($response->json('message'))->not->toBe('')
            ->and($response->json('data.sheet.id'))->toBe($product->id);
    });

    it('holds the save until the type\'s required field is answered', function (): void {
        $owner = payingUser();
        $type = goodsType();
        $watts = ServiceAttribute::factory()->create(['name' => 'Potencia', 'data_type' => 'number', 'is_active' => true]);
        $type->serviceAttributes()->attach($watts->id, ['is_required' => true, 'sort_order' => 0]);

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Taladro', 'service_type_id' => $type->id])
            ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.'.$watts->id);

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Taladro', 'service_type_id' => $type->id, 'attribute_values' => [$watts->id => '700']])->assertCreated();

        expect($owner->business->products()->sole()->attribute_values)->toBe([(string) $watts->id => '700']);
    });

    it('refuses what the web\'s sheet refuses', function (array $body, string $field): void {
        $owner = payingUser();
        productOf($owner, ['name' => 'Taladro', 'code' => 'TAL-01']);
        $service = ServiceType::factory()->create();
        $body = collect($body)->map(fn (mixed $value): mixed => $value === ':service-type' ? $service->id : $value)->all();

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Otro', ...$body])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($owner->business->products()->count())->toBe(1);
    })->with([
        'a name already taken' => [['name' => 'Taladro'], 'name'],
        'a name of one letter' => [['name' => 'A'], 'name'],
        'a code already taken' => [['code' => 'TAL-01'], 'code'],
        'a price that is not a number' => [['price' => 'mucho'], 'price'],
        'a quantity that is not a number' => [['stock' => 'varios'], 'stock'],
        'a type of services' => [['service_type_id' => ':service-type'], 'service_type_id'],
    ]);

    it('changes only what the body carries and keeps the rest, the type\'s values among them', function (): void {
        $owner = payingUser();
        $type = goodsType();
        $watts = ServiceAttribute::factory()->create(['data_type' => 'number', 'is_active' => true]);
        $color = ServiceAttribute::factory()->create(['data_type' => 'text', 'is_active' => true]);
        $type->serviceAttributes()->attach($watts->id, ['is_required' => false, 'sort_order' => 0]);
        $type->serviceAttributes()->attach($color->id, ['is_required' => false, 'sort_order' => 1]);
        $product = productOf($owner, ['name' => 'Taladro', 'service_type_id' => $type->id, 'price' => 100, 'description' => 'Con maletín', 'attribute_values' => [(string) $watts->id => '700', (string) $color->id => 'azul']]);

        asProductsApp($owner)->putJson("/api/v1/products/{$product->id}", ['name' => 'Taladro grande', 'attribute_values' => [$watts->id => '900']])->assertOk();

        $product->refresh();

        expect($product->name)->toBe('Taladro grande')
            ->and((float) $product->price)->toBe(100.0)
            ->and($product->description)->toBe('Con maletín')
            ->and($product->attribute_values)->toBe([(string) $watts->id => '900', (string) $color->id => 'azul']);
    });

    it('clears a box sent empty, and keeps its own name and code when saved again', function (): void {
        $owner = payingUser();
        $product = productOf($owner, ['name' => 'Taladro', 'code' => 'TAL-01', 'price' => 50, 'description' => 'Hola']);

        asProductsApp($owner)->putJson("/api/v1/products/{$product->id}", ['name' => 'Taladro', 'code' => 'TAL-01', 'price' => '', 'description' => null])->assertOk();

        expect($product->refresh()->price)->toBeNull()->and($product->description)->toBeNull()->and($product->code)->toBe('TAL-01');
    });

    it('reads a number typed in a text box as the text it spells, and a list in a single answer as a refusal, never a crash', function (): void {
        $owner = payingUser();
        $type = goodsType();
        $yes = ServiceAttribute::factory()->create(['data_type' => 'boolean', 'is_active' => true]);
        $type->serviceAttributes()->attach($yes->id, ['is_required' => false, 'sort_order' => 0]);

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => ['x'], 'code' => ['y']])->assertUnprocessable()->assertJsonValidationErrors('name');
        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Taladro', 'service_type_id' => $type->id, 'attribute_values' => [$yes->id => ['x']]])
            ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.'.$yes->id);
        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 456])->assertCreated();

        expect($owner->business->products()->pluck('name')->all())->toBe(['456']);
    });

    it('refuses a boolean in a text box as a missing name, never a crash', function (): void {
        $owner = payingUser();

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => true])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('ignores the list answers of a type no longer picked instead of blaming a field that is gone', function (): void {
        $owner = payingUser();
        $before = goodsType();
        $after = goodsType();
        $colors = ServiceAttribute::factory()->create(['data_type' => 'list', 'is_multiple' => true, 'is_active' => true]);
        $before->serviceAttributes()->attach($colors->id, ['is_required' => false, 'sort_order' => 0]);

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Taladro', 'service_type_id' => $after->id, 'attribute_values' => [$colors->id => ['1', '2']]])->assertCreated();

        expect($owner->business->products()->count())->toBe(1);
    });

    it('says the name is taken when renaming to one a deleted product still holds, never a crash', function (): void {
        $owner = payingUser();
        $gone = productOf($owner, ['name' => 'Zapato']);
        $gone->delete();
        $mine = productOf($owner, ['name' => 'Bota']);

        asProductsApp($owner)->putJson("/api/v1/products/{$mine->id}", ['name' => 'Zapato'])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('does not lock a product out of its edits because its type is no longer one the sheet offers', function (): void {
        $owner = payingUser();
        $retired = goodsType(['is_active' => false]);
        $product = productOf($owner, ['name' => 'Taladro', 'service_type_id' => $retired->id, 'price' => 5]);

        asProductsApp($owner)->putJson("/api/v1/products/{$product->id}", ['price' => '9'])->assertOk();

        expect((float) $product->refresh()->price)->toBe(9.0)->and($product->service_type_id)->toBe($retired->id);
    });

    it('writes nothing but the sheet\'s boxes', function (): void {
        $owner = payingUser();
        $other = payingUser();

        asProductsApp($owner)->postJson('/api/v1/products', ['name' => 'Taladro', 'business_id' => $other->business_id, 'id' => 9999999])->assertCreated();

        expect($owner->business->products()->count())->toBe(1)->and(Product::query()->where('business_id', $other->business_id)->count())->toBe(0);
    });
});

describe('the row\'s own actions', function (): void {
    it('flips the stock from the row', function (): void {
        $owner = payingUser();
        $product = productOf($owner, ['name' => 'Taladro']);

        $out = asProductsApp($owner)->postJson("/api/v1/products/{$product->id}/toggle")->assertOk()->json('data.row');
        $back = asProductsApp($owner)->postJson("/api/v1/products/{$product->id}/toggle")->assertOk()->json('data.row');

        expect($out['inStock'])->toBeFalse()->and($out['outNote'])->toBe(__('client.products.out_note'))->and($back['inStock'])->toBeTrue();
    });

    it('deletes with a way back, and brings it back with its price', function (): void {
        $owner = payingUser();
        $product = productOf($owner, ['name' => 'Taladro', 'price' => 8]);

        $deleted = asProductsApp($owner)->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        expect($deleted->json('message'))->toBe(__('client.products.deleted'))
            ->and($deleted->json('data.undo'))->toBe(__('client.products.undo'))
            ->and($owner->business->products()->count())->toBe(0);

        asProductsApp($owner)->postJson("/api/v1/products/{$product->id}/restore")->assertOk()->assertJsonPath('message', __('client.products.restored'));

        expect((float) $owner->business->products()->sole()->price)->toBe(8.0);
    });

    it('keeps a product a customer asked about and says to mark it out of stock instead', function (): void {
        $owner = payingUser();
        $product = productOf($owner, ['name' => 'Taladro']);
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id]);
        $reading = ConversationAnalysis::factory()->create(['business_id' => $owner->business_id, 'conversation_id' => $thread->id]);
        ConversationQuestion::query()->create([
            'business_id' => $owner->business_id, 'conversation_id' => $thread->id, 'conversation_analysis_id' => $reading->id,
            'product_id' => $product->id, 'question' => '¿Tienen taladros?', 'resolved_by' => QuestionResolution::Assistant,
        ]);

        asProductsApp($owner)->deleteJson("/api/v1/products/{$product->id}")
            ->assertUnprocessable()->assertJsonPath('errors.product.0', __('client.products.delete_held'));

        expect($owner->business->products()->count())->toBe(1);
    });

    it('has nothing to bring back that was never deleted', function (): void {
        $owner = payingUser();
        $product = productOf($owner);

        asProductsApp($owner)->postJson("/api/v1/products/{$product->id}/restore")->assertNotFound();
    });
});

it('puts Productos in the catalog group of the app\'s menu, after Servicios', function (): void {
    $menu = asProductsApp(payingUser())->getJson('/api/v1/menu')->assertOk()->json('data');
    $group = collect($menu)->firstWhere('label', __('menu.catalog'));

    expect(collect($group['children'])->pluck('screen')->all())->toBe(['my-services', 'my-products']);
});
