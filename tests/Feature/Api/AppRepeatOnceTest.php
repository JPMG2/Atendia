<?php

declare(strict_types=1);

use App\Http\Middleware\AnswerRepeatOnce;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
| On a slow network the answer to a creation can be lost after the row was
| saved; the phone retries the very same request. It is done once.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    // Every offer change reaches the assistant's knowledge: indexing it would call OpenAI.
    Queue::fake();
});

function asRepeatingApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

it('creates once when the same creation arrives twice, and answers both with the first outcome', function (): void {
    $owner = payingUser();
    $body = ['name' => 'Corte de cabello', 'price_type' => 'fixed', 'price' => '8', 'duration_minutes' => 30];

    $first = asRepeatingApp($owner)->postJson('/api/v1/services', $body)->assertCreated();
    $again = asRepeatingApp($owner)->postJson('/api/v1/services', $body)->assertCreated();

    expect($owner->business->services()->count())->toBe(1)
        ->and($again->json())->toBe($first->json());
});

it('creates again after the first one was deleted: a new act, not a retry', function (): void {
    $owner = payingUser();
    $body = ['name' => 'Corte', 'price_type' => 'free', 'duration_minutes' => 30];

    $id = asRepeatingApp($owner)->postJson('/api/v1/services', $body)->assertCreated()->json('data.sheet.id');
    asRepeatingApp($owner)->deleteJson("/api/v1/services/{$id}")->assertOk();
    asRepeatingApp($owner)->postJson('/api/v1/services', $body)->assertCreated();

    expect($owner->business->services()->count())->toBe(1);
});

it('creates twice when the bodies differ: it is two creations, not a retry', function (): void {
    $owner = payingUser();

    asRepeatingApp($owner)->postJson('/api/v1/services', ['name' => 'Corte', 'price_type' => 'free', 'duration_minutes' => 30])->assertCreated();
    asRepeatingApp($owner)->postJson('/api/v1/services', ['name' => 'Tinte', 'price_type' => 'free', 'duration_minutes' => 30])->assertCreated();

    expect($owner->business->services()->count())->toBe(2);
});

it('keeps no refusal: a fixed request goes through', function (): void {
    $owner = payingUser();

    asRepeatingApp($owner)->postJson('/api/v1/services', ['name' => '', 'price_type' => 'free'])->assertUnprocessable();
    asRepeatingApp($owner)->postJson('/api/v1/services', ['name' => '', 'price_type' => 'free'])->assertUnprocessable();

    expect($owner->business->services()->count())->toBe(0);
});

it('never hands one person\'s answer to another', function (): void {
    $mine = payingUser();
    $theirs = payingUser();
    $body = ['name' => 'Corte', 'price_type' => 'free', 'duration_minutes' => 30];

    asRepeatingApp($mine)->postJson('/api/v1/services', $body)->assertCreated();
    asRepeatingApp($theirs)->postJson('/api/v1/services', $body)->assertCreated();

    // Each business is counted from inside its own tenant: row security keeps the other out.
    $count = fn (User $owner): int => app(Tenant::class)->for($owner->business_id, fn (): int => $owner->business->services()->count());

    expect($count($mine))->toBe(1)
        ->and($count($theirs))->toBe(1);
});

it('guards every creation and send the phone may retry, and never a switch nor a resend on purpose', function (): void {
    $guarded = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array(AnswerRepeatOnce::class.':guard', $route->gatherMiddleware(), true))
        ->map(fn ($route): string => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri())
        ->sort()->values()->all();

    expect($guarded)->toBe([
        'POST api/v1/admin/catalogs/{master}',
        'POST api/v1/admin/support/{ticket}/reply',
        'POST api/v1/agenda/bookings',
        'POST api/v1/customers/{customer}/opt-in',
        'POST api/v1/products',
        'POST api/v1/services',
        'POST api/v1/services/categories',
        'POST api/v1/support',
        'POST api/v1/team/departments',
        'POST api/v1/team/invitations',
    ]);
});
