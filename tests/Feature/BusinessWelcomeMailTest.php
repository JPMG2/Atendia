<?php

declare(strict_types=1);

use App\Events\BusinessCreated;
use App\Listeners\SendBusinessWelcome;
use App\Livewire\Forms\Business\BusinessForm;
use App\Mail\BusinessWelcome;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The business welcome mail
|--------------------------------------------------------------------------
| Fired by event when the tenant is BORN (unlike the company mail, sent
| inline: this happens N times and more effects will hang off it). It goes
| to the account's address — the only one existing at that moment — and
| greets exactly once per business.
*/

/** Bare component satisfying Form::__construct(Component $component, $propertyName). */
class WelcomeMailHostComponent extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}

/** @return array{0: BusinessForm, 1: User} */
function welcomeReadyForm(): array
{
    $user = User::factory()->create()->refresh();
    test()->actingAs($user);

    $province = Province::factory()->create();
    $sector = BusinessSector::factory()->create(['code' => 'salud']);
    BusinessActivity::factory()->create(['code' => 'consultorio-medico', 'business_sector_id' => $sector->id]);

    $form = new BusinessForm(new WelcomeMailHostComponent, 'form');
    $form->setup();

    $form->data->name = 'Clínica Vida';
    $form->data->country_id = $province->country_id;
    $form->data->province_id = $province->id;
    $form->data->sector = 'salud';
    $form->data->activity = 'consultorio-medico';

    return [$form, $user];
}

test('creating the business queues the welcome to the account address', function (): void {
    Mail::fake();

    [$form, $user] = welcomeReadyForm();

    $form->saveIdentity();

    Mail::assertQueued(BusinessWelcome::class, fn (BusinessWelcome $mail): bool => $mail->hasTo($user->email));
});

test('walking back and saving again never greets twice', function (): void {
    Mail::fake();

    [$form] = welcomeReadyForm();

    $form->saveIdentity();

    $form->data->name = 'Clínica Vida Plena';

    $form->saveIdentity();

    Mail::assertQueuedCount(1);
});

test('the listener hangs off the event, so tomorrow\'s effects can join it', function (): void {
    Event::fake();

    Event::assertListening(BusinessCreated::class, SendBusinessWelcome::class);
});

test('the mail names the business and points at the one next step', function (): void {
    $business = Business::factory()->create(['name' => 'Clínica Vida']);

    $mail = new BusinessWelcome($business);

    expect($mail->envelope()->subject)->toBe(__('mail.business_welcome.subject', ['name' => 'Clínica Vida']))
        ->and($mail->render())
        ->toContain('Clínica Vida')
        ->toContain(__('mail.business_welcome.cta'))
        ->toContain(route('onboarding'));
});

test('a failed save greets nobody', function (): void {
    Mail::fake();

    [$form] = welcomeReadyForm();

    $form->data->sector = 'rubro-inventado';

    try {
        $form->saveIdentity();
    } catch (ValidationException) {
        // The point is what did NOT happen below.
    }

    Mail::assertNothingQueued();
});
