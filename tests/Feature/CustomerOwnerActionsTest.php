<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The directory: the team sees and edits it, the owner decides (2026-11-02)
|--------------------------------------------------------------------------
| Exporting the whole directory, merging two fiches and asking for the offers
| permit are the owner's. An agent still sees every customer and edits them.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** @return array{owner: User, agent: User, customer: Customer, twin: Customer} */
function directoryTeam(): array
{
    $owner = payingUser()->refresh();
    $agent = User::factory()->create(['business_id' => $owner->business_id]);
    $agent->syncRoles(['agent']);

    [$customer, $twin] = app(Tenant::class)->for($owner->business_id, fn (): array => [
        Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana', 'email' => 'ana@mail.test']),
        Customer::factory()->create(['business_id' => $owner->business_id, 'name' => 'Ana P.', 'email' => 'ana@mail.test']),
    ]);

    return ['owner' => $owner, 'agent' => $agent->refresh(), 'customer' => $customer, 'twin' => $twin];
}

it('lets an agent see and edit every customer, without the owner buttons', function (): void {
    ['agent' => $agent, 'customer' => $customer] = directoryTeam();
    $this->actingAs($agent);

    livewire('customers.index')
        ->assertSee('Ana')
        ->assertDontSeeHtml('export-menu')
        ->call('show', $customer->id)
        ->assertDontSee(__('client.customers.merge_button'))
        ->assertDontSee(__('client.customers.opt_in_button'))
        ->set('customerForm.notes', 'Prefiere la tarde')
        ->call('saveCustomer');

    expect(app(Tenant::class)->for($agent->business_id, fn (): ?string => $customer->fresh()->notes))->toBe('Prefiere la tarde');
});

it('refuses the owner actions to an agent on the web, saying why', function (): void {
    ['agent' => $agent, 'customer' => $customer, 'twin' => $twin] = directoryTeam();
    $this->actingAs($agent);

    livewire('customers.index')
        ->call('show', $customer->id)
        ->call('mergeCustomer')
        ->call('requestOptIn');

    expect(app(Tenant::class)->for($agent->business_id, fn (): bool => Customer::query()->whereKey($twin->id)->exists()))->toBeTrue()
        ->and(app(Tenant::class)->for($agent->business_id, fn () => $customer->fresh()->marketing_opt_in_requested_at))->toBeNull();
});

it('still shows the owner the merge and the permit', function (): void {
    ['owner' => $owner, 'customer' => $customer] = directoryTeam();
    $this->actingAs($owner);

    livewire('customers.index')
        ->assertSeeHtml('export-menu')
        ->call('show', $customer->id)
        ->assertSee(__('client.customers.merge_button'))
        ->assertSee(__('client.customers.opt_in_button'));
});

it('does not let an agent download the whole directory', function (): void {
    ['owner' => $owner, 'agent' => $agent] = directoryTeam();

    $this->actingAs($agent)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->assertForbidden();
    $this->actingAs($owner)->get(route('reports.show', ['report' => 'clientes', 'format' => 'csv']))->assertOk();
});

it('closes the same doors in the app API', function (): void {
    ['owner' => $owner, 'agent' => $agent, 'customer' => $customer, 'twin' => $twin] = directoryTeam();
    $agent->business->subscription->forceFill(['status' => 'active', 'current_period_ends_at' => now()->addDays(20)])->save();
    app('auth')->forgetGuards();
    $api = $this->withToken(appInstallToken($agent))->withHeaders(apiDeviceHeaders());

    $sheet = $api->getJson("/api/v1/customers/{$customer->id}")->assertOk()->json('data.sheet');
    expect($sheet['duplicate'])->toBeNull()
        ->and($sheet['optIn']['button'])->toBeNull();

    $api->postJson("/api/v1/customers/{$customer->id}/merge", ['duplicate_id' => $twin->id])
        ->assertForbidden()->assertJsonPath('message', __('client.customers.owner_only'));
    $api->postJson("/api/v1/customers/{$customer->id}/opt-in")
        ->assertForbidden()->assertJsonPath('message', __('client.customers.owner_only'));
    $api->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Ana María'])->assertOk();

    app('auth')->forgetGuards();
    $ownerSheet = $this->withToken(appInstallToken($owner))->withHeaders(apiDeviceHeaders())->getJson("/api/v1/customers/{$customer->id}")->json('data.sheet');
    expect($ownerSheet['duplicate']['id'])->toBe($twin->id)
        ->and($ownerSheet['optIn']['button'])->toBe(__('client.customers.opt_in_button'));
});
