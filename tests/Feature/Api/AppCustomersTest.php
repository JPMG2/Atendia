<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Services\Tenant;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Clientes" over the API
|--------------------------------------------------------------------------
| The phone reads the web's own directory (the cut its export uses) and writes
| the fiche through the web's rules: the words, the filters and the checks are
| the web's, and a customer of another business is never reachable.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

function asCustomersApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** @param  array<string, mixed>  $attributes */
function customerOf(User $owner, array $attributes = []): Customer
{
    return Customer::factory()->create(['business_id' => $owner->business_id, ...$attributes]);
}

describe('the list', function (): void {
    it('lists the freshest first with the web\'s own badges, hour and words', function (): void {
        $owner = payingUser();
        $old = customerOf($owner, ['name' => 'Rosa Linares', 'phone' => '584165550133', 'email' => 'rosa@correo.com', 'last_activity_at' => now()->subDays(3)]);
        $fresh = customerOf($owner, ['name' => 'Valentina Pérez', 'phone' => '584145550121', 'birthday' => now()->subYears(30)->startOfMonth()->addDays(2), 'marketing_opt_in_at' => now()->subWeek(), 'last_activity_at' => now()]);
        $nameless = customerOf($owner, ['name' => null, 'profile_name' => null, 'phone' => '584125550166', 'last_activity_at' => now()->subDay()]);

        $data = asCustomersApp($owner)->getJson('/api/v1/customers')->assertOk()->json('data');
        $rows = collect($data['rows']);

        expect($rows->pluck('id')->all())->toBe([$fresh->id, $nameless->id, $old->id])
            ->and($rows[0]['badges'])->toBe([
                ['kind' => 'birthday', 'label' => __('client.customers.badge_birthday')],
                ['kind' => 'optin', 'label' => __('client.customers.badge_optin')],
            ])
            ->and($rows[0]['last'])->toMatch('/^\d{2}:\d{2}$/')
            ->and($rows[2]['last'])->toMatch('/^\d{2}\/\d{2}$/')
            ->and($rows[2]['email'])->toBe('rosa@correo.com')
            ->and($rows[1]['name'])->toBe(__('client.conversations.anonymous'))
            ->and($rows[1]['avatarName'])->toBe('584125550166')
            ->and($data['count'])->toBe(3)
            ->and($data['countLabel'])->toBe(trans_choice('client.customers.count', 3, ['count' => 3]))
            ->and($data['any'])->toBeTrue()
            ->and($data['copy']['title'])->toBe(__('client.customers.title'))
            ->and($data['copy']['filters']['optin'])->toBe(__('client.customers.filter_optin'))
            ->and($data['copy']['fields']['birthday'])->toBe(__('client.customers.field_birthday'));
    });

    it('cuts the audience as the web does, and searches without accents over name, phone and e-mail', function (): void {
        $owner = payingUser();
        $maria = customerOf($owner, ['name' => 'María Núñez', 'phone' => '584140000001', 'email' => 'mn@correo.com', 'marketing_opt_in_at' => now(), 'last_activity_at' => now()]);
        $jose = customerOf($owner, ['name' => 'José Pérez', 'phone' => '584240000002', 'email' => 'jp@tienda.com', 'birthday' => now()->subYears(40), 'last_activity_at' => now()->subHour()]);

        $ids = fn (string $query): array => collect(asCustomersApp($owner)->getJson('/api/v1/customers?'.$query)->assertOk()->json('data.rows'))->pluck('id')->all();

        expect($ids('filter=optin'))->toBe([$maria->id])
            ->and($ids('filter=birthday'))->toBe($jose->birthday->month === now()->month ? [$jose->id] : [])
            ->and($ids('search=maria nunez'))->toBe([$maria->id])
            ->and($ids('search=5842400'))->toBe([$jose->id])
            ->and($ids('search=tienda.com'))->toBe([$jose->id])
            ->and($ids('search=nadie'))->toBe([]);
    });

    it('walks in tranches of fifteen and says whether there are more', function (): void {
        $owner = payingUser();

        foreach (range(1, 17) as $index) {
            customerOf($owner, ['name' => "Cliente {$index}", 'last_activity_at' => now()->subMinutes($index)]);
        }

        $first = asCustomersApp($owner)->getJson('/api/v1/customers')->assertOk()->json('data');
        $second = asCustomersApp($owner)->getJson('/api/v1/customers?page=2')->assertOk()->json('data');

        expect($first['rows'])->toHaveCount(15)->and($first['hasMore'])->toBeTrue()->and($first['count'])->toBe(17)
            ->and($second['rows'])->toHaveCount(2)->and($second['hasMore'])->toBeFalse()
            ->and($second['rows'][0]['name'])->toBe('Cliente 16');
    });

    it('says there are no customers at all, apart from the filters that find none', function (): void {
        $owner = payingUser();

        expect(asCustomersApp($owner)->getJson('/api/v1/customers')->assertOk()->json('data.any'))->toBeFalse();

        customerOf($owner, ['name' => 'Ana']);

        expect(asCustomersApp($owner)->getJson('/api/v1/customers?search=zzz')->assertOk()->json('data'))
            ->toMatchArray(['any' => true, 'count' => 0, 'rows' => []]);
    });

    it('refuses a filter the web does not have', function (): void {
        asCustomersApp(payingUser())->getJson('/api/v1/customers?filter=vip')->assertUnprocessable()->assertJsonValidationErrors('filter');
    });

    it('never lists the customers of another business', function (): void {
        $owner = payingUser();
        $other = payingUser();
        customerOf($other, ['name' => 'Ajena']);

        expect(asCustomersApp($owner)->getJson('/api/v1/customers')->assertOk()->json('data.rows'))->toBeEmpty();
    });

    it('is closed to a call without a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/customers')->assertUnauthorized();
    });
});

describe('the fiche', function (): void {
    it('opens with what is known, what the assistant learned, the twin and where the consent stands', function (): void {
        $owner = payingUser();
        $maria = customerOf($owner, [
            'name' => 'Valentina Pérez', 'profile_name' => 'Vale', 'phone' => '584145550121', 'country_code' => 've', 'email' => 'vale@correo.com',
            'birthday' => '1990-11-14', 'notes' => 'Prefiere la tarde.', 'marketing_opt_in_requested_at' => now()->subDays(2),
            'ai_extracted' => ['birthday' => ['value' => '14/11/1990', 'confidence' => 'high', 'source' => 'ai', 'at' => now()->toIso8601String()]],
        ]);
        customerOf($owner, ['name' => 'Vale P.', 'email' => 'vale@correo.com', 'phone' => '584145550999']);

        $data = asCustomersApp($owner)->getJson("/api/v1/customers/{$maria->id}")->assertOk()->json('data');
        $sheet = $data['sheet'];

        expect($sheet['title'])->toBe('Valentina Pérez')
            ->and($sheet['since'])->toBe(__('client.customers.since', ['date' => $maria->first_seen_at->inBusinessTime()->format('d/m/Y')]))
            ->and($sheet['country'])->toBe('VE')
            ->and($sheet['pushName'])->toBe(__('client.customers.push_name', ['name' => 'Vale']))
            ->and($sheet['values'])->toBe(['name' => 'Valentina Pérez', 'email' => 'vale@correo.com', 'birthday' => '1990-11-14', 'notes' => 'Prefiere la tarde.'])
            ->and($sheet['learned']['birthday'])->toBe(__('client.customers.ai_badge', ['field' => __('client.customers.field_birthday'), 'value' => '14/11/1990']))
            ->and($sheet['duplicate']['title'])->toBe(__('client.customers.merge_title'))
            ->and($sheet['duplicate']['body'])->toBe(__('client.customers.merge_body', ['name' => 'Vale P.']))
            ->and($sheet['optIn']['granted'])->toBeFalse()
            ->and($sheet['optIn']['text'])->toBe(__('client.customers.opt_in_requested', ['date' => $maria->marketing_opt_in_requested_at->inBusinessTime()->format('d/m/Y')]))
            ->and($sheet['optIn']['button'])->toBe(__('client.customers.opt_in_button'))
            ->and($data['copy']['mergeConfirmBody'])->toBe(__('client.customers.merge_confirm_body'));
    });

    it('offers no consent button once the customer said yes', function (): void {
        $owner = payingUser();
        $customer = customerOf($owner, ['marketing_opt_in_at' => now()->subDay()]);

        $sheet = asCustomersApp($owner)->getJson("/api/v1/customers/{$customer->id}")->assertOk()->json('data.sheet');

        expect($sheet['optIn']['granted'])->toBeTrue()
            ->and($sheet['optIn']['button'])->toBeNull()
            ->and($sheet['optIn']['text'])->toBe(__('client.customers.opt_in_yes', ['date' => $customer->marketing_opt_in_at->inBusinessTime()->format('d/m/Y')]))
            ->and($sheet['duplicate'])->toBeNull();
    });

    it('does not reach the customer of another business, whatever it is asked to do', function (): void {
        $owner = payingUser();
        $foreign = customerOf(payingUser(), ['name' => 'Ajena', 'email' => 'x@y.test']);

        asCustomersApp($owner)->getJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
        asCustomersApp($owner)->putJson("/api/v1/customers/{$foreign->id}", ['name' => 'Mía'])->assertNotFound();
        asCustomersApp($owner)->postJson("/api/v1/customers/{$foreign->id}/opt-in")->assertNotFound();
        asCustomersApp($owner)->postJson("/api/v1/customers/{$foreign->id}/merge", ['duplicate_id' => 1])->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): string => Customer::query()->whereKey($foreign->id)->value('name')))->toBe('Ajena');
    });
});

describe('saving the fiche', function (): void {
    it('changes only the boxes sent and says it in the web\'s words', function (): void {
        $owner = payingUser();
        $customer = customerOf($owner, ['name' => 'Valentina', 'email' => 'vale@correo.com', 'birthday' => '1990-11-14', 'notes' => 'Nota vieja.']);

        $response = asCustomersApp($owner)->putJson("/api/v1/customers/{$customer->id}", ['notes' => '  Prefiere la tarde.  '])->assertOk();

        $customer->refresh();

        expect($customer->notes)->toBe('Prefiere la tarde.')
            ->and($customer->name)->toBe('Valentina')
            ->and($customer->email)->toBe('vale@correo.com')
            ->and($customer->birthday->toDateString())->toBe('1990-11-14')
            ->and($response->json('message'))->not->toBe('')
            ->and($response->json('data.sheet.values.notes'))->toBe('Prefiere la tarde.');
    });

    it('clears a box sent empty, as the sheet does', function (): void {
        $owner = payingUser();
        $customer = customerOf($owner, ['name' => 'Valentina', 'email' => 'vale@correo.com', 'birthday' => '1990-11-14', 'notes' => 'Hola']);

        asCustomersApp($owner)->putJson("/api/v1/customers/{$customer->id}", ['email' => '', 'birthday' => null, 'notes' => ''])->assertOk();

        $customer->refresh();

        expect($customer->email)->toBeNull()->and($customer->birthday)->toBeNull()->and($customer->notes)->toBeNull()->and($customer->name)->toBe('Valentina');
    });

    it('refuses what the web\'s sheet refuses', function (array $body, string $field): void {
        $owner = payingUser();
        $customer = customerOf($owner, ['name' => 'Valentina']);

        asCustomersApp($owner)->putJson("/api/v1/customers/{$customer->id}", $body)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($customer->refresh()->name)->toBe('Valentina');
    })->with([
        'an e-mail that is not one' => [['email' => 'no-es-correo'], 'email'],
        'a birthday in the future' => [['birthday' => '2999-01-01'], 'birthday'],
        'a birthday that is not a date in the web\'s format' => [['birthday' => '14/11/1990'], 'birthday'],
        'a name of one letter' => [['name' => 'A'], 'name'],
        'notes past the limit' => [['notes' => str_repeat('a', 2001)], 'notes'],
    ]);

    it('writes nothing but the four human boxes', function (): void {
        $owner = payingUser();
        $other = payingUser();
        $customer = customerOf($owner, ['name' => 'Valentina', 'phone' => '584145550121', 'conversations_count' => 3]);

        asCustomersApp($owner)->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Vale', 'phone' => '1', 'business_id' => $other->business_id, 'conversations_count' => 99, 'marketing_opt_in_at' => now()->toDateTimeString(),
        ])->assertOk();

        $customer->refresh();

        expect($customer->name)->toBe('Vale')
            ->and($customer->phone)->toBe('584145550121')
            ->and($customer->business_id)->toBe($owner->business_id)
            ->and($customer->conversations_count)->toBe(3)
            ->and($customer->marketing_opt_in_at)->toBeNull();
    });
});

describe('asking for consent', function (): void {
    it('asks over the business\'s own WhatsApp and stamps the ask', function (): void {
        config()->set('services.evolution.url', 'http://evolution.test');
        config()->set('services.evolution.key', 'test-key');
        Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);

        $owner = payingUser();
        $owner->business->forceFill(['whatsapp_instance' => 'demo', 'whatsapp_connected_at' => now()])->save();
        $customer = customerOf($owner, ['name' => 'Valentina']);

        $response = asCustomersApp($owner)->postJson("/api/v1/customers/{$customer->id}/opt-in")
            ->assertOk()->assertJsonPath('message', __('client.customers.opt_in_sent'));

        expect($customer->refresh()->marketing_opt_in_requested_at)->not->toBeNull()
            ->and($response->json('data.sheet.optIn.text'))->toContain(now($owner->business->localTimezone())->format('d/m/Y'));
        Http::assertSentCount(1);
    });

    it('asks nobody who already said yes or who is blocked, however many times it is called', function (): void {
        config()->set('services.evolution.url', 'http://evolution.test');
        config()->set('services.evolution.key', 'test-key');
        Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);

        $owner = payingUser();
        $owner->business->forceFill(['whatsapp_instance' => 'demo', 'whatsapp_connected_at' => now()])->save();
        $said = customerOf($owner, ['marketing_opt_in_at' => now()->subDay()]);
        $blocked = customerOf($owner, ['blocked_at' => now()->subDay()]);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$said->id}/opt-in")->assertNotFound();
        asCustomersApp($owner)->postJson("/api/v1/customers/{$blocked->id}/opt-in")->assertNotFound();

        Http::assertNothingSent();
    });

    it('says to connect the WhatsApp when there is none, and sends nothing', function (): void {
        Http::fake();
        $owner = payingUser();
        $customer = customerOf($owner);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$customer->id}/opt-in")
            ->assertUnprocessable()->assertJsonValidationErrors('opt_in')->assertJsonPath('errors.opt_in.0', __('client.customers.opt_in_unavailable'));

        expect($customer->refresh()->marketing_opt_in_requested_at)->toBeNull();
        Http::assertNothingSent();
    });
});

describe('merging two fiches', function (): void {
    it('moves the twin\'s threads here and the twin dies', function (): void {
        $owner = payingUser();
        $keep = customerOf($owner, ['name' => 'Valentina', 'email' => 'vale@correo.com', 'notes' => null]);
        $twin = customerOf($owner, ['name' => 'Vale', 'email' => 'vale@correo.com', 'notes' => 'Vieja ficha.']);
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id, 'customer_id' => $twin->id]);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$keep->id}/merge", ['duplicate_id' => $twin->id])
            ->assertOk()->assertJsonPath('message', __('client.customers.merged'))->assertJsonPath('data.sheet.duplicate', null);

        expect(Customer::query()->whereKey($twin->id)->exists())->toBeFalse()
            ->and($thread->refresh()->customer_id)->toBe($keep->id)
            ->and($keep->refresh()->notes)->toBe('Vieja ficha.');
    });

    it('keeps the block and the oldest dates of the twin it swallows', function (): void {
        $owner = payingUser();
        $keep = customerOf($owner, ['email' => 'vale@correo.com', 'first_seen_at' => now()->subDays(5), 'last_activity_at' => now()->subDay()]);
        $twin = customerOf($owner, ['email' => 'vale@correo.com', 'blocked_at' => now()->subDays(3), 'marketing_opt_in_requested_at' => now()->subDays(2), 'first_seen_at' => now()->subDays(40), 'last_activity_at' => now()]);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$keep->id}/merge", ['duplicate_id' => $twin->id])->assertOk();

        $keep->refresh();

        expect($keep->blocked_at)->not->toBeNull()
            ->and($keep->marketing_opt_in_requested_at)->not->toBeNull()
            ->and($keep->first_seen_at->isSameDay(now()->subDays(40)))->toBeTrue()
            ->and($keep->last_activity_at->isToday())->toBeTrue();
    });

    it('folds only the twin the phone showed: another one, or none named, folds nothing', function (): void {
        $owner = payingUser();
        $keep = customerOf($owner, ['email' => 'vale@correo.com']);
        $shown = customerOf($owner, ['email' => 'vale@correo.com']);
        $other = customerOf($owner, ['email' => 'vale@correo.com']);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$keep->id}/merge", ['duplicate_id' => $other->id])->assertStatus(409);
        asCustomersApp($owner)->postJson("/api/v1/customers/{$keep->id}/merge")->assertUnprocessable()->assertJsonValidationErrors('duplicate_id');

        // The twin it names is the lowest id, the same every time, and the sheet says which.
        expect(asCustomersApp($owner)->getJson("/api/v1/customers/{$keep->id}")->json('data.sheet.duplicate.id'))->toBe($shown->id)
            ->and(Customer::query()->count())->toBe(3);
    });

    it('has nothing to merge for a customer without a twin', function (): void {
        $owner = payingUser();
        $alone = customerOf($owner, ['email' => 'solo@correo.com']);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$alone->id}/merge", ['duplicate_id' => 1])->assertNotFound();

        expect(Customer::query()->count())->toBe(1);
    });

    it('never merges with the twin of another business', function (): void {
        $owner = payingUser();
        $mine = customerOf($owner, ['email' => 'vale@correo.com']);
        $foreign = customerOf(payingUser(), ['email' => 'vale@correo.com']);

        asCustomersApp($owner)->postJson("/api/v1/customers/{$mine->id}/merge", ['duplicate_id' => $foreign->id])->assertNotFound();

        expect(app(Tenant::class)->for($foreign->business_id, fn (): bool => Customer::query()->whereKey($foreign->id)->exists()))->toBeTrue();
    });
});

it('puts Clientes in the app\'s menu', function (): void {
    $owner = payingUser();

    $labels = collect(asCustomersApp($owner)->getJson('/api/v1/menu')->assertOk()->json('data'))->pluck('screen')->all();

    expect($labels)->toContain('customers');
});
