<?php

declare(strict_types=1);

use App\Livewire\Forms\Client\ServiceCategoryForm;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceAttribute;
use App\Models\ServiceCategory;
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
| "Tus servicios" over the API
|--------------------------------------------------------------------------
| The phone reads the web's own list cut and writes through the web's rules and
| Actions: the shelves, the window, the words and the checks are the web's, and
| a service of another business is never reachable.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    // Every offer change reaches the assistant's knowledge: indexing it would call OpenAI.
    Queue::fake();
});

function asServicesApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** @param  array<string, mixed>  $attributes */
function serviceOf(User $owner, array $attributes = []): Service
{
    return Service::factory()->create(['business_id' => $owner->business_id, 'service_type_id' => null, ...$attributes]);
}

describe('the list', function (): void {
    it('says every row in the web\'s words: the badge, the price, the length and the deposit', function (): void {
        $owner = payingUser();
        serviceOf($owner, ['name' => 'Corte', 'price_type' => 'fixed', 'price' => 8, 'duration_minutes' => 30, 'description' => 'Corte y peinado.']);
        serviceOf($owner, ['name' => 'Tinte', 'price_type' => 'from', 'price' => 25, 'deposit' => 5, 'is_featured' => true]);
        serviceOf($owner, ['name' => 'Consulta', 'price_type' => 'free']);
        serviceOf($owner, ['name' => 'Evento', 'price_type' => 'talk']);
        serviceOf($owner, ['name' => 'Pedicure', 'price' => null, 'is_active' => false]);

        $data = asServicesApp($owner)->getJson('/api/v1/services')->assertOk()->json('data');
        $rows = collect($data['rows'])->keyBy('name');

        expect($data['grouped'])->toBeFalse()
            ->and($rows['Corte']['price'])->toBe(['kind' => 'amount', 'label' => '$ '.Service::query()->where('name', 'Corte')->first()->priceAmount()])
            ->and($rows['Corte']['minutes'])->toBe('30 min')
            ->and($rows['Corte']['status'])->toBe(__('client.services.active'))
            ->and($rows['Corte']['editLabel'])->toBe(__('client.services.edit', ['name' => 'Corte']))
            ->and($rows['Corte']['toggleLabel'])->toBe(__('client.services.pause', ['name' => 'Corte']))
            ->and($rows['Tinte']['price']['label'])->toStartWith('Desde $')
            ->and($rows['Tinte']['deposit'])->toBe(__('client.services.deposit_short', ['amount' => 5]))
            ->and($rows['Consulta']['price'])->toBe(['kind' => 'free', 'label' => __('client.services.price_free')])
            ->and($rows['Evento']['price']['label'])->toBe(__('client.services.price_talk'))
            ->and($rows['Pedicure']['price'])->toBe(['kind' => 'none', 'label' => __('client.services.no_price')])
            ->and($rows['Pedicure']['status'])->toBe(__('client.services.paused'))
            ->and($rows['Pedicure']['toggleLabel'])->toBe(__('client.services.resume', ['name' => 'Pedicure']))
            // Featured first, as the web promises.
            ->and($data['rows'][0]['name'])->toBe('Tinte')
            ->and($data['meter'])->toBe(['label' => __('client.services.meter', ['done' => 4, 'total' => 5]), 'percent' => 80])
            ->and($data['countLabel'])->toBe(trans_choice('client.services.count', 5, ['count' => 5]))
            ->and($data['copy']['add'])->toBe(__('client.services.add'))
            ->and($data['copy']['sheet']['fields']['priceType'])->toBe(__('client.services.field_price_type'))
            // The phone's category input stops where the web's rule does.
            ->and($data['copy']['category']['max'])->toBe(ServiceCategoryForm::NAME_MAX);
    });

    it('groups on shelves once one exists: Destacados, then the shelves in their order, then the loose ones', function (): void {
        $owner = payingUser();
        $nails = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'Manos', 'sort_order' => 2]);
        $hair = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'Cabello', 'sort_order' => 1]);
        serviceOf($owner, ['name' => 'Corte', 'service_category_id' => $hair->id, 'is_featured' => true]);
        serviceOf($owner, ['name' => 'Manicure', 'service_category_id' => $nails->id]);
        serviceOf($owner, ['name' => 'Suelto']);

        $data = asServicesApp($owner)->getJson('/api/v1/services')->assertOk()->json('data');

        expect($data['grouped'])->toBeTrue()
            ->and(collect($data['groups'])->pluck('name')->all())->toBe([__('client.services.featured_title'), 'Cabello', 'Manos', __('client.services.uncategorized')])
            ->and(collect($data['groups'])->pluck('featured')->all())->toBe([true, false, false, false])
            ->and($data['groups'][0]['rows'][0]['name'])->toBe('Corte')
            ->and($data['groups'][1]['key'])->toBe((string) $hair->id)
            ->and($data['groups'][3]['key'])->toBe('')
            // A featured row keeps its own shelf too.
            ->and($data['groups'][1]['rows'][0]['name'])->toBe('Corte');
    });

    it('keeps a folded shelf\'s header and hides its rows, and counts only what can still load', function (): void {
        $owner = payingUser();
        $hair = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'Cabello']);
        serviceOf($owner, ['name' => 'Corte', 'service_category_id' => $hair->id]);
        serviceOf($owner, ['name' => 'Suelto']);

        $data = asServicesApp($owner)->getJson('/api/v1/services?'.http_build_query(['collapsed' => [(string) $hair->id]]))->assertOk()->json('data');
        $shelf = collect($data['groups'])->firstWhere('key', (string) $hair->id);

        expect($shelf['collapsed'])->toBeTrue()->and($shelf['rows'])->toBe([])->and($shelf['count'])->toBe(1)
            ->and($data['shown'])->toBe(1)->and($data['total'])->toBe(1);
    });

    it('folds the loose shelf, whose key is empty, as it folds any other', function (): void {
        $owner = payingUser();
        $hair = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'Cabello']);
        serviceOf($owner, ['name' => 'Corte', 'service_category_id' => $hair->id]);
        serviceOf($owner, ['name' => 'Suelto']);

        // The phone sends `collapsed[]=` for the shelf without a name: the framework reads it as null.
        $data = asServicesApp($owner)->getJson('/api/v1/services?collapsed[]=')->assertOk()->json('data');
        $loose = collect($data['groups'])->firstWhere('key', '');

        expect($loose['collapsed'])->toBeTrue()->and($loose['rows'])->toBe([])->and($loose['count'])->toBe(1);
    });

    it('walks in windows of eight and says whether there is more', function (): void {
        $owner = payingUser();

        foreach (range(1, 11) as $index) {
            serviceOf($owner, ['name' => "Servicio {$index}"]);
        }

        $first = asServicesApp($owner)->getJson('/api/v1/services')->assertOk()->json('data');
        $more = asServicesApp($owner)->getJson('/api/v1/services?visible=16')->assertOk()->json('data');

        expect($first['rows'])->toHaveCount(8)->and($first['hasMore'])->toBeTrue()->and($first['total'])->toBe(11)
            ->and($first['showing'])->toBe(__('pagination.showing', ['shown' => 8, 'total' => 11]))
            ->and($more['rows'])->toHaveCount(11)->and($more['hasMore'])->toBeFalse();
    });

    it('flattens the shelves while searching or filtering, and finds without accents', function (): void {
        $owner = payingUser();
        $hair = ServiceCategory::factory()->create(['business_id' => $owner->business_id]);
        serviceOf($owner, ['name' => 'Peluquería canina', 'service_category_id' => $hair->id]);
        serviceOf($owner, ['name' => 'Manicure', 'is_active' => false]);

        $search = asServicesApp($owner)->getJson('/api/v1/services?search=peluqueria')->assertOk()->json('data');
        $paused = asServicesApp($owner)->getJson('/api/v1/services?filter=paused')->assertOk()->json('data');

        expect($search['grouped'])->toBeFalse()->and(collect($search['rows'])->pluck('name')->all())->toBe(['Peluquería canina'])
            ->and(collect($paused['rows'])->pluck('name')->all())->toBe(['Manicure'])
            ->and($paused['countLabel'])->toBe(trans_choice('client.services.count', 1, ['count' => 1]));
    });

    it('says there is nothing yet, without a fake meter', function (): void {
        $data = asServicesApp(payingUser())->getJson('/api/v1/services')->assertOk()->json('data');

        expect($data['any'])->toBeFalse()->and($data['rows'])->toBe([])->and($data['meter']['percent'])->toBe(0)
            ->and($data['copy']['emptyTitle'])->toBe(__('client.services.empty_title'));
    });

    it('refuses a filter the web does not have', function (): void {
        asServicesApp(payingUser())->getJson('/api/v1/services?filter=vip')->assertUnprocessable()->assertJsonValidationErrors('filter');
    });

    it('never lists the services of another business', function (): void {
        $owner = payingUser();
        serviceOf(payingUser(), ['name' => 'Ajeno']);

        expect(asServicesApp($owner)->getJson('/api/v1/services')->assertOk()->json('data.rows'))->toBe([]);
    });

    it('is closed to an agent', function (): void {
        $owner = payingUser();
        $agent = User::factory()->create(['business_id' => $owner->business_id]);
        $agent->syncRoles(['agent']);

        asServicesApp($agent)->getJson('/api/v1/services')->assertForbidden();
        asServicesApp($agent)->postJson('/api/v1/services', ['name' => 'Corte'])->assertForbidden();
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/services')->assertUnauthorized();
    });
});

describe('the sheet', function (): void {
    it('opens blank, with the name a suggestion chip typed, the shelves and the types to pick', function (): void {
        $owner = payingUser();
        $shelf = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'Cabello']);
        $type = ServiceType::factory()->create(['name' => 'Peluquería']);

        $data = asServicesApp($owner)->getJson('/api/v1/services/new?name='.urlencode('Peinado de novia'))->assertOk()->json('data');

        expect($data['sheet']['title'])->toBe(__('client.services.sheet_new_title'))
            ->and($data['sheet']['id'])->toBeNull()
            ->and($data['sheet']['values']['name'])->toBe('Peinado de novia')
            ->and($data['sheet']['values']['price_type'])->toBe('fixed')
            ->and($data['sheet']['values']['is_active'])->toBeTrue()
            ->and($data['sheet']['categories'])->toBe([['id' => $shelf->id, 'name' => 'Cabello']])
            ->and(collect($data['sheet']['types'])->pluck('id')->all())->toContain($type->id)
            ->and($data['copy']['sheet']['helps']['type'])->toBe(__('client.services.type_help'));
    });

    it('opens on a stored service with its price as it was typed and its type\'s own fields', function (): void {
        $owner = payingUser();
        $type = ServiceType::factory()->create(['name' => 'Mesa']);
        $seats = ServiceAttribute::factory()->create(['name' => 'Personas', 'data_type' => 'number', 'is_active' => true]);
        $type->serviceAttributes()->attach($seats->id, ['is_required' => true, 'sort_order' => 0, 'label_override' => 'Comensales']);
        $service = serviceOf($owner, ['name' => 'Mesa para dos', 'service_type_id' => $type->id, 'price' => 12000, 'attribute_values' => [(string) $seats->id => '4']]);

        $sheet = asServicesApp($owner)->getJson("/api/v1/services/{$service->id}")->assertOk()->json('data.sheet');

        expect($sheet['title'])->toBe(__('client.services.sheet_title', ['name' => 'Mesa para dos']))
            ->and($sheet['values']['price'])->toBe('12000')
            ->and($sheet['values']['attribute_values'])->toBe([(string) $seats->id => '4'])
            ->and($sheet['attributes'][0])->toMatchArray(['id' => $seats->id, 'label' => 'Comensales', 'data_type' => 'number', 'is_required' => true])
            ->and($sheet['deleteTitle'])->toBe(__('client.services.delete_title', ['name' => 'Mesa para dos']));
    });

    it('grows with a picked type\'s fields, and only for the types the sheet offers', function (): void {
        $owner = payingUser();
        $type = ServiceType::factory()->create();
        $field = ServiceAttribute::factory()->create(['data_type' => 'boolean', 'is_active' => true]);
        $type->serviceAttributes()->attach($field->id, ['is_required' => false, 'sort_order' => 0]);
        $goods = ServiceType::factory()->create(['service_modality_id' => ServiceModality::factory()->create(['code' => 'producto'])->id]);

        expect(asServicesApp($owner)->getJson("/api/v1/services/types/{$type->id}")->assertOk()->json('data.attributes.0.id'))->toBe($field->id);
        asServicesApp($owner)->getJson("/api/v1/services/types/{$goods->id}")->assertNotFound();
    });

    it('does not reach the service of another business', function (): void {
        $owner = payingUser();
        $foreign = serviceOf(payingUser(), ['name' => 'Ajeno']);

        asServicesApp($owner)->getJson("/api/v1/services/{$foreign->id}")->assertNotFound();
        asServicesApp($owner)->putJson("/api/v1/services/{$foreign->id}", ['name' => 'Mío'])->assertNotFound();
        asServicesApp($owner)->deleteJson("/api/v1/services/{$foreign->id}")->assertNotFound();
        asServicesApp($owner)->postJson("/api/v1/services/{$foreign->id}/toggle")->assertNotFound();
        asServicesApp($owner)->postJson("/api/v1/services/{$foreign->id}/restore")->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): string => Service::query()->whereKey($foreign->id)->value('name')))->toBe('Ajeno');
    });
});

describe('saving a service', function (): void {
    it('creates one as the web\'s sheet does and says it in the web\'s words', function (): void {
        $owner = payingUser();
        $shelf = ServiceCategory::factory()->create(['business_id' => $owner->business_id]);

        $response = asServicesApp($owner)->postJson('/api/v1/services', [
            'name' => '  Corte   de cabello ', 'service_category_id' => $shelf->id, 'price_type' => 'fixed', 'price' => '8', 'duration_minutes' => 30,
            'deposit' => '2', 'description' => 'Corte y peinado.', 'prep_note' => 'Llega con el cabello seco', 'is_featured' => true,
        ])->assertCreated();

        $service = $owner->business->services()->sole();

        expect($service->name)->toBe('Corte de cabello')
            ->and($service->service_category_id)->toBe($shelf->id)
            ->and((float) $service->price)->toBe(8.0)
            ->and($service->duration_minutes)->toBe(30)
            ->and($service->is_featured)->toBeTrue()
            ->and($service->is_active)->toBeTrue()
            ->and($response->json('message'))->not->toBe('')
            ->and($response->json('data.sheet.id'))->toBe($service->id);
    });

    it('holds the save until the type\'s required field is answered, and keys the values by attribute id', function (): void {
        $owner = payingUser();
        $type = ServiceType::factory()->create();
        $seats = ServiceAttribute::factory()->create(['name' => 'Personas', 'data_type' => 'number', 'is_active' => true]);
        $note = ServiceAttribute::factory()->create(['name' => 'Nota', 'data_type' => 'text', 'is_active' => true]);
        $type->serviceAttributes()->attach($seats->id, ['is_required' => true, 'sort_order' => 0]);
        $type->serviceAttributes()->attach($note->id, ['is_required' => false, 'sort_order' => 1]);

        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 'Mesa', 'service_type_id' => $type->id])
            ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.'.$seats->id);

        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 'Mesa', 'service_type_id' => $type->id, 'attribute_values' => [$seats->id => '4']])->assertCreated();

        // The empty field is noise on the row: only the answered one is stored.
        expect($owner->business->services()->sole()->attribute_values)->toBe([(string) $seats->id => '4']);
    });

    it('refuses what the web\'s sheet refuses', function (array $body, string $field): void {
        $owner = payingUser();
        serviceOf($owner, ['name' => 'Corte']);
        $foreignShelf = ServiceCategory::factory()->create(['business_id' => payingUser()->business_id]);
        $goods = ServiceType::factory()->create(['service_modality_id' => ServiceModality::factory()->create(['code' => 'producto'])->id]);
        $body = collect($body)->map(fn (mixed $value): mixed => $value === ':foreign-shelf' ? $foreignShelf->id : ($value === ':goods' ? $goods->id : $value))->all();

        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 'Otro', ...$body])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($owner->business->services()->count())->toBe(1);
    })->with([
        'a name already taken' => [['name' => 'Corte'], 'name'],
        'a name of one letter' => [['name' => 'A'], 'name'],
        'a price type the web does not have' => [['price_type' => 'gratis'], 'price_type'],
        'a price that is not a number' => [['price' => 'mucho'], 'price'],
        'a length of zero' => [['duration_minutes' => 0], 'duration_minutes'],
        'a length of two days' => [['duration_minutes' => 2880], 'duration_minutes'],
        'a shelf of another business' => [['service_category_id' => ':foreign-shelf'], 'service_category_id'],
        'a type of products' => [['service_type_id' => ':goods'], 'service_type_id'],
    ]);

    it('changes only what the body carries and keeps the rest, the type\'s values among them', function (): void {
        $owner = payingUser();
        $type = ServiceType::factory()->create();
        $seats = ServiceAttribute::factory()->create(['data_type' => 'number', 'is_active' => true]);
        $other = ServiceAttribute::factory()->create(['data_type' => 'text', 'is_active' => true]);
        $type->serviceAttributes()->attach($seats->id, ['is_required' => false, 'sort_order' => 0]);
        $type->serviceAttributes()->attach($other->id, ['is_required' => false, 'sort_order' => 1]);
        $service = serviceOf($owner, ['name' => 'Mesa', 'service_type_id' => $type->id, 'price' => 100, 'description' => 'Para dos', 'attribute_values' => [(string) $seats->id => '4', (string) $other->id => 'vista']]);

        asServicesApp($owner)->putJson("/api/v1/services/{$service->id}", ['name' => 'Mesa grande', 'attribute_values' => [$seats->id => '6']])->assertOk();

        $service->refresh();

        expect($service->name)->toBe('Mesa grande')
            ->and((float) $service->price)->toBe(100.0)
            ->and($service->description)->toBe('Para dos')
            ->and($service->attribute_values)->toBe([(string) $seats->id => '6', (string) $other->id => 'vista']);
    });

    it('clears a box sent empty, and a free or to-be-agreed service carries no amount', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner, ['name' => 'Consulta', 'price' => 50, 'deposit' => 10, 'description' => 'Hola']);

        asServicesApp($owner)->putJson("/api/v1/services/{$service->id}", ['deposit' => '', 'description' => null, 'price_type' => 'free'])->assertOk();

        $service->refresh();

        expect($service->deposit)->toBeNull()->and($service->description)->toBeNull()->and($service->price)->toBeNull()->and($service->price_type)->toBe('free');
    });

    it('keeps its own name when it is saved again, and never reads a body that is not text', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner, ['name' => 'Corte']);

        asServicesApp($owner)->putJson("/api/v1/services/{$service->id}", ['name' => 'Corte', 'price' => '9'])->assertOk();
        asServicesApp($owner)->postJson('/api/v1/services', ['name' => ['x'], 'description' => ['y'], 'price_type' => ['z'], 'is_active' => 'false'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        expect($owner->business->services()->count())->toBe(1);
    });

    it('reads a number typed in a text box as the text it spells, and a list in a single answer as a refusal, never a crash', function (): void {
        $owner = payingUser();
        $type = ServiceType::factory()->create();
        $yes = ServiceAttribute::factory()->create(['data_type' => 'boolean', 'is_active' => true]);
        $type->serviceAttributes()->attach($yes->id, ['is_required' => false, 'sort_order' => 0]);

        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 123, 'price_type' => 5])->assertUnprocessable()->assertJsonValidationErrors('price_type');
        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 'Mesa', 'service_type_id' => $type->id, 'attribute_values' => [$yes->id => ['x']]])
            ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.'.$yes->id);
        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 456])->assertCreated();

        expect($owner->business->services()->pluck('name')->all())->toBe(['456']);
    });

    it('does not lock a service out of its edits because its type is no longer one the sheet offers', function (): void {
        $owner = payingUser();
        $retired = ServiceType::factory()->create(['is_active' => false]);
        $service = serviceOf($owner, ['name' => 'Corte', 'service_type_id' => $retired->id, 'price' => 5]);

        asServicesApp($owner)->putJson("/api/v1/services/{$service->id}", ['price' => '9'])->assertOk();
        // Choosing it for another service is still refused: only the carried type is let through.
        $other = serviceOf($owner, ['name' => 'Tinte']);
        asServicesApp($owner)->putJson("/api/v1/services/{$other->id}", ['service_type_id' => $retired->id])->assertUnprocessable()->assertJsonValidationErrors('service_type_id');

        expect((float) $service->refresh()->price)->toBe(9.0)->and($service->service_type_id)->toBe($retired->id);
    });

    it('writes nothing but the sheet\'s boxes', function (): void {
        $owner = payingUser();
        $other = payingUser();

        asServicesApp($owner)->postJson('/api/v1/services', ['name' => 'Corte', 'business_id' => $other->business_id, 'id' => 9999999])->assertCreated();

        expect($owner->business->services()->count())->toBe(1)->and(Service::query()->where('business_id', $other->business_id)->count())->toBe(0);
    });
});

describe('the row\'s own actions', function (): void {
    it('pauses and resumes from the row', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner, ['name' => 'Corte']);

        $paused = asServicesApp($owner)->postJson("/api/v1/services/{$service->id}/toggle")->assertOk()->json('data.row');
        $resumed = asServicesApp($owner)->postJson("/api/v1/services/{$service->id}/toggle")->assertOk()->json('data.row');

        expect($paused['active'])->toBeFalse()->and($paused['status'])->toBe(__('client.services.paused'))
            ->and($resumed['active'])->toBeTrue();
    });

    it('deletes with a way back, and brings it back with its price', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner, ['name' => 'Corte', 'price' => 8]);

        $deleted = asServicesApp($owner)->deleteJson("/api/v1/services/{$service->id}")->assertOk();

        expect($deleted->json('message'))->toBe(__('client.services.deleted'))
            ->and($deleted->json('data.undo'))->toBe(__('client.services.undo'))
            ->and($owner->business->services()->count())->toBe(0);

        asServicesApp($owner)->postJson("/api/v1/services/{$service->id}/restore")->assertOk()->assertJsonPath('message', __('client.services.restored'));

        expect((float) $owner->business->services()->sole()->price)->toBe(8.0);
    });

    it('keeps a service with history and says to pause it instead', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner, ['name' => 'Corte']);
        Appointment::factory()->create(['business_id' => $owner->business_id, 'service_id' => $service->id]);

        asServicesApp($owner)->deleteJson("/api/v1/services/{$service->id}")
            ->assertUnprocessable()->assertJsonPath('errors.service.0', __('client.services.delete_held'));

        expect($owner->business->services()->count())->toBe(1);
    });

    it('has nothing to bring back that was never deleted', function (): void {
        $owner = payingUser();
        $service = serviceOf($owner);

        asServicesApp($owner)->postJson("/api/v1/services/{$service->id}/restore")->assertNotFound();
    });
});

describe('the shelves', function (): void {
    it('creates one with the web\'s name rules and answers with it, ready to pick', function (): void {
        $owner = payingUser();

        $created = asServicesApp($owner)->postJson('/api/v1/services/categories', ['name' => '  Manos   y pies '])->assertCreated();

        expect($created->json('data.category.name'))->toBe('Manos y pies')
            ->and($owner->business->serviceCategories()->sole()->name)->toBe('Manos y pies');

        asServicesApp($owner)->postJson('/api/v1/services/categories', ['name' => 'Manos y pies'])->assertUnprocessable()->assertJsonValidationErrors('name');
        asServicesApp($owner)->postJson('/api/v1/services/categories', ['name' => str_repeat('a', 31)])->assertUnprocessable()->assertJsonValidationErrors('name');
        asServicesApp($owner)->postJson('/api/v1/services/categories', ['name' => ['x']])->assertUnprocessable();
    });

    it('moves one to a position, in the order the assistant offers', function (): void {
        $owner = payingUser();
        $a = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'A1', 'sort_order' => 1]);
        $b = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'B1', 'sort_order' => 2]);
        $c = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'C1', 'sort_order' => 3]);

        asServicesApp($owner)->putJson("/api/v1/services/categories/{$c->id}/position", ['position' => 0])->assertOk();

        expect($owner->business->serviceCategories()->orderBy('sort_order')->pluck('name')->all())->toBe(['C1', 'A1', 'B1']);
    });

    it('moves one next to the shelf the screen shows beside it, however many empty ones lie between', function (): void {
        $owner = payingUser();
        $a = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'A1', 'sort_order' => 1]);
        $b = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'B1', 'sort_order' => 2]);
        $c = ServiceCategory::factory()->create(['business_id' => $owner->business_id, 'name' => 'C1', 'sort_order' => 3]);
        $order = fn (): array => $owner->business->serviceCategories()->orderBy('sort_order')->pluck('name')->all();

        // A has no services, so the screen draws B and C: "up" for C is before B, not "first".
        asServicesApp($owner)->putJson("/api/v1/services/categories/{$c->id}/position", ['near' => $b->id, 'after' => false])->assertOk();
        expect($order())->toBe(['A1', 'C1', 'B1']);

        asServicesApp($owner)->putJson("/api/v1/services/categories/{$a->id}/position", ['near' => $b->id, 'after' => true])->assertOk();
        expect($order())->toBe(['C1', 'B1', 'A1']);

        asServicesApp($owner)->putJson("/api/v1/services/categories/{$a->id}/position", ['near' => $a->id])->assertNotFound();
        asServicesApp($owner)->putJson("/api/v1/services/categories/{$a->id}/position", [])->assertUnprocessable();
    });

    it('never moves the shelf of another business', function (): void {
        $owner = payingUser();
        $foreign = ServiceCategory::factory()->create(['business_id' => payingUser()->business_id]);

        asServicesApp($owner)->putJson("/api/v1/services/categories/{$foreign->id}/position", ['position' => 0])->assertNotFound();
        asServicesApp($owner)->putJson("/api/v1/services/categories/{$foreign->id}/position", ['position' => -1])->assertUnprocessable();
    });
});

it('puts the catalog group with its services in the app\'s menu', function (): void {
    $menu = asServicesApp(payingUser())->getJson('/api/v1/menu')->assertOk()->json('data');
    $group = collect($menu)->firstWhere('label', __('menu.catalog'));

    expect($group)->not->toBeNull()->and(collect($group['children'])->pluck('screen')->all())->toBe(['my-services', 'my-products']);
});
