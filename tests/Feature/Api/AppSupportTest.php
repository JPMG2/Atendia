<?php

declare(strict_types=1);

use App\Actions\Support\CreateSupportTicket;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketOpened;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\EvolutionApi;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Soporte técnico" over the API
|--------------------------------------------------------------------------
| The phone writes the report through the web widget's own Action and words: the same
| rules, the same code, and the team hears of it by the same mail. The image the
| widget offers waits for the phone's picker.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);
    Queue::fake();
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');

    $whatsapp = mock(EvolutionApi::class);
    $whatsapp->shouldReceive('sendText')->andReturnTrue()->byDefault();
    app()->instance(EvolutionApi::class, $whatsapp);
});

function asSupportApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

it('opens with the widget\'s words, the kinds she offers and the screens the app has', function (): void {
    $owner = payingUser();

    $data = asSupportApp($owner)->getJson('/api/v1/support')->assertOk()->json('data');

    expect($data['copy']['title'])->toBe(__('support.title'))
        ->and($data['copy']['bodyHint'])->toBe(__('support.body_hint'))
        ->and($data['copy']['thanksTitle'])->toBe(__('support.thanks_title'))
        ->and(collect($data['kinds'])->pluck('value')->all())->toContain('problem')
        ->and($data['kind'])->toBe(collect($data['kinds'])->first()['value'])
        // The menu cut to the screens a phone can show: a web-only screen is no choice here.
        ->and(collect($data['screens'])->pluck('label')->all())->toContain('Clientes')->not->toContain('Compañía');
});

it('writes the report through the web\'s Action and says its code in the web\'s words', function (): void {
    $owner = payingUser();

    $response = asSupportApp($owner)->postJson('/api/v1/support', ['body' => 'El botón de guardar no hace nada con el precio vacío', 'kind' => 'problem', 'screen' => 'customers'])
        ->assertCreated();
    $ticket = SupportTicket::query()->firstOrFail();

    expect($response->json('message'))->toBe(__('support.sent', ['code' => $ticket->code]))
        ->and($response->json('data.code'))->toBe($ticket->code)
        ->and($ticket->business_id)->toBe($owner->business_id)
        ->and($ticket->screen)->toBe('customers')
        ->and($ticket->kind->code)->toBe('problem')
        ->and($ticket->status)->toBe(SupportTicketStatus::New)
        ->and($ticket->context['agent'])->toBe('Aliadito app')
        ->and($ticket->context['route'])->toBe('customers');
    Mail::assertQueued(SupportTicketOpened::class);
});

it('takes the report without a screen, as the web does when it was opened from nowhere', function (): void {
    $owner = payingUser();

    asSupportApp($owner)->postJson('/api/v1/support', ['body' => 'No sé qué pasó pero algo anda raro', 'kind' => 'idea'])->assertCreated();

    expect(SupportTicket::query()->firstOrFail()->screen)->toBeNull();
});

it('refuses what the widget refuses', function (array $body, string $field): void {
    $owner = payingUser();

    asSupportApp($owner)->postJson('/api/v1/support', ['body' => 'Esto es un problema de verdad', 'kind' => 'problem', ...$body])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(SupportTicket::query()->count())->toBe(0);
})->with([
    'no text' => [['body' => null], 'body'],
    'under ten characters' => [['body' => 'no va'], 'body'],
    'past the limit' => [['body' => str_repeat('a', 2001)], 'body'],
    'a kind that does not exist' => [['kind' => 'nope'], 'kind'],
    'no kind' => [['kind' => null], 'kind'],
    'a screen name past its limit' => [['screen' => str_repeat('a', 81)], 'screen'],
    'a screen the app does not offer' => [['screen' => 'cualquier <script>'], 'screen'],
]);

it('reads a body that is not text as nothing sent, never a crash', function (): void {
    $owner = payingUser();

    asSupportApp($owner)->postJson('/api/v1/support', ['body' => ['x'], 'kind' => true, 'screen' => ['y']])
        ->assertUnprocessable()->assertJsonValidationErrors(['body', 'kind']);
});

it('drops a NUL byte instead of failing the insert, and says the web\'s own sentence when the save fails', function (): void {
    $owner = payingUser();

    asSupportApp($owner)->postJson('/api/v1/support', ['body' => "Algo falla al guardar\0 el servicio", 'kind' => 'problem'])->assertCreated();

    expect(SupportTicket::query()->firstOrFail()->body)->toBe('Algo falla al guardar el servicio');

    app()->bind(CreateSupportTicket::class, fn () => throw new RuntimeException('down'));

    asSupportApp($owner)->postJson('/api/v1/support', ['body' => 'Otro reporte que no se puede guardar', 'kind' => 'problem'])
        ->assertStatus(500)->assertJsonPath('message', __('support.failed'));
});

it('lets a teammate who is not the owner report too, as the web\'s top bar does', function (): void {
    $owner = payingUser();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    asSupportApp($agent)->getJson('/api/v1/support')->assertOk();
    asSupportApp($agent)->postJson('/api/v1/support', ['body' => 'Una pregunta para el equipo de soporte', 'kind' => 'question'])->assertCreated();

    expect(SupportTicket::query()->count())->toBe(1);
});

it('is closed to a call without a token', function (): void {
    test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/support')->assertUnauthorized();
    test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/support', [])->assertUnauthorized();
});

it('names the profile row as the web\'s button is named', function (): void {
    $owner = payingUser();

    asSupportApp($owner)->getJson('/api/v1/account')->assertOk()->assertJsonPath('data.support_label', __('support.open'));
});
