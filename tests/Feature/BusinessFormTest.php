<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Livewire\Forms\Business\BusinessForm;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Country;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business form (wizard slices)
|--------------------------------------------------------------------------
| One form, one save per step: identity creates the tenant and hangs the
| user off it; connection updates its own columns and nothing else. The form
| is exercised directly through a bare host component, same trick as
| BaseFormTest — the wizard blades wire it up in a later step.
*/

/** Bare component satisfying Form::__construct(Component $component, $propertyName). */
class BusinessFormHostComponent extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}

function makeBusinessForm(): BusinessForm
{
    $form = new BusinessForm(new BusinessFormHostComponent, 'form');
    $form->setup();

    return $form;
}

/** @return array{0: BusinessForm, 1: User, 2: Province} */
function signedInBlankForm(): array
{
    // Refreshed because a factory model only carries the inserted attributes,
    // and strict mode turns reading the absent business_id into an exception.
    $user = User::factory()->create()->refresh();
    test()->actingAs($user);

    $province = Province::factory()->create();

    // The sector and activity rules point at the catalog, so the chips have
    // to exist there — the activity scoped under its sector.
    $sector = BusinessSector::factory()->create(['code' => 'salud']);
    BusinessActivity::factory()->create(['code' => 'consultorio-medico', 'business_sector_id' => $sector->id]);

    return [makeBusinessForm(), $user, $province];
}

test('the identity step creates the business, links the user and copies the billing email', function (): void {
    [$form, $user, $province] = signedInBlankForm();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'consultorio-medico';

    $notification = $form->saveIdentity();

    $business = Business::sole();

    expect($notification->type)->toBe(NotificationType::Success)
        ->and($business->name)->toBe('Clínica Vida')
        ->and($business->province_id)->toBe($province->id)
        ->and($business->billing_email)->toBe($user->email)
        ->and($user->refresh()->business_id)->toBe($business->id)
        ->and($form->recordId)->toBe($business->id);
});

test('a second identity save updates the same business instead of creating another', function (): void {
    [$form, , $province] = signedInBlankForm();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'consultorio-medico';

    $form->saveIdentity();

    $form->data->name = 'Clínica Vida Plena';

    $form->saveIdentity();

    expect(Business::count())->toBe(1)
        ->and(Business::sole()->name)->toBe('Clínica Vida Plena');
});

test('the identity step demands a sector, because the services step builds on it', function (): void {
    [$form, , $province] = signedInBlankForm();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;

    $form->saveIdentity();
})->throws(ValidationException::class);

test('a sector missing from the catalog is rejected, even a well-formed one', function (): void {
    [$form, , $province] = signedInBlankForm();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'rubro-inventado';

    $form->saveIdentity();
})->throws(ValidationException::class);

test('the identity save declares the primary activity of the tenant', function (): void {
    [$form, , $province] = signedInBlankForm();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'consultorio-medico';

    $form->saveIdentity();

    expect(Business::sole()->primaryActivity()->code)->toBe('consultorio-medico');
});

test('an activity of another sector is rejected, even an existing one', function (): void {
    [$form, , $province] = signedInBlankForm();

    // Exists and is active, but hangs off a different sector than the chosen one.
    BusinessActivity::factory()->create(['code' => 'peluqueria']);

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'peluqueria';

    $form->saveIdentity();
})->throws(ValidationException::class);

test('a province of another country is rejected even if the id exists', function (): void {
    [$form, , $province] = signedInBlankForm();

    $foreign = Province::factory()->create();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $foreign->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'consultorio-medico';

    $form->saveIdentity();
})->throws(ValidationException::class);

test('setup hydrates the form from the signed-in user\'s business', function (): void {
    $business = Business::factory()->create(['name' => 'La Esquina']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    $form = makeBusinessForm();

    expect($form->recordId)->toBe($business->id)
        ->and($form->data->name)->toBe('La Esquina');
});

test('the connection step writes its own columns and nothing else', function (): void {
    $business = Business::factory()->create(['name' => 'La Esquina']);
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    $form = makeBusinessForm();

    $form->data->whatsapp_number = '+54 9 341 512 4408';
    $form->data->email = 'hola@laesquina.com';
    // The screen name typed here must NOT travel: it is another step's column.
    $form->data->name = 'Renombrada a escondidas';

    $notification = $form->saveConnection();

    $business->refresh();

    expect($notification->type)->toBe(NotificationType::Success)
        ->and($business->whatsapp_number)->toBe('+54 9 341 512 4408')
        ->and($business->email)->toBe('hola@laesquina.com')
        ->and($business->name)->toBe('La Esquina');
});

test('a malformed business email bounces before touching the record', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $user->business()->associate($business)->save();
    test()->actingAs($user);

    $form = makeBusinessForm();

    $form->data->email = 'no-es-un-correo';

    $form->saveConnection();
})->throws(ValidationException::class);

test('the connection step warns instead of creating a half-made business', function (): void {
    test()->actingAs(User::factory()->create()->refresh());

    $form = makeBusinessForm();

    $form->data->whatsapp_number = '+54 9 341 512 4408';

    expect($form->saveConnection()->type)->toBe(NotificationType::Error)
        ->and(Business::count())->toBe(0);
});

test('changing the country empties the province, so a foreign one cannot linger', function (): void {
    [$form, , $province] = signedInBlankForm();

    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;

    $form->data->country_id = Country::factory()->create()->id;
    $form->updatedDataCountryId();

    expect($form->data->province_id)->toBeNull();
});
