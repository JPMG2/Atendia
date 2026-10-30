<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Enums\PlatformEventKind;
use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\ComplianceEvent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\PlatformContact;
use App\Models\PlatformEvent;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Adopción, Qué salió mal, Radar and Cumplimiento in the app
|--------------------------------------------------------------------------
| Read only, from the same pieces the web screens read and in their words,
| each behind the key of its web screen.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function activityToken(string $role = 'admin'): string
{
    Mail::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->syncRoles([$role]);

    $id = test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
        ->assertStatus(202)->json('challenge.id');

    $code = '';
    Mail::assertQueued(DeviceChallengeCode::class, function (DeviceChallengeCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return test()->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => $id, 'code' => $code])->assertOk()->json('token');
}

function activityCall(string $token, string $path): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->getJson('/api/v1/admin/'.$path);
}

test('adoption brings the web\'s funnel, tabs and rows, with why each account is listed', function (): void {
    User::factory()->create(['email' => 'sola@negocio.test', 'created_at' => now()->subDays(10)]);

    $data = activityCall(activityToken(), 'adoption')->assertOk()->json('data');

    expect($data['title'])->toBe(__('adoption.title'))
        ->and($data['flow']['steps'][0]['label'])->toBe(__('adoption.steps.registered'))
        ->and(collect($data['tabs'])->pluck('label')->all())->toBe([__('adoption.tabs.stalled'), __('adoption.tabs.starting'), __('adoption.tabs.active')]);

    $row = collect($data['rows'])->firstWhere('email', 'sola@negocio.test');
    expect($row['business'])->toBe(__('adoption.no_business'))
        ->and($row['why'])->toBe(__('adoption.why.registered'))
        ->and($row['rule'])->not->toBeNull()
        ->and($row['target'])->toBeNull();
});

test('what went wrong opens on the worst tab with rows, and names the business behind most of them', function (): void {
    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    foreach ([90, 120, 200] as $minutes) {
        $thread = Conversation::factory()->create(['business_id' => $business->id, 'last_message_at' => now()->subMinutes($minutes)]);
        ConversationMessage::factory()->create([
            'business_id' => $business->id, 'conversation_id' => $thread->id, 'direction' => MessageDirection::In,
            'body' => '¿Están abiertos?', 'created_at' => now()->subMinutes($minutes),
        ]);
    }

    $data = activityCall(activityToken(), 'incidents')->assertOk()->json('data');
    $tab = collect($data['tabs'])->firstWhere('value', 'unanswered');

    expect($data['open_tab'])->toBe('unanswered')
        ->and($tab['badge'])->toBe(3)
        ->and($tab['concentrated']['line'])->toBe(__('incidents.concentrated_all', ['count' => 3, 'business' => 'Laboratorio Vida']))
        ->and($tab['concentrated']['target'])->toBe(['screen' => 'admin.businesses', 'query' => ['negocio' => $business->id]])
        ->and(collect($tab['rows'])->pluck('waiting')->all())->toContain(trans_choice('incidents.waiting_hours', 1, ['count' => 1]), trans_choice('incidents.waiting_hours', 3, ['count' => 3]))
        ->and($tab['rows'][0]['excerpt'])->toBe('¿Están abiertos?');
});

test('the radar puts the shared person first with the web\'s tag, and says when nobody is shared', function (): void {
    PlatformContact::factory()->create(['phone' => '5491100000001', 'businesses_count' => 1, 'last_activity_at' => now()]);
    PlatformContact::factory()->create(['phone' => '5491100000002', 'businesses_count' => 3, 'last_activity_at' => now()->subDays(5)]);
    $token = activityToken();

    $data = activityCall($token, 'contacts')->assertOk()->json('data');
    expect($data['rows'][0]['phone'])->toBe('5491100000002')
        ->and($data['rows'][0]['shared'])->toBe(__('contacts.shared_tag', ['count' => 3]))
        ->and($data['rows'][1]['shared'])->toBeNull()
        ->and($data['none_shared'])->toBeNull()
        ->and(collect($data['cards'])->firstWhere('key', 'shared')['value'])->toBe(1);

    PlatformContact::query()->update(['businesses_count' => 1]);
    expect(activityCall($token, 'contacts')->json('data.none_shared'))->toBe(__('contacts.none_shared'));
});

test('compliance lists, filters and searches as the web, and opens one business\'s history', function (): void {
    $business = Business::factory()->create(['name' => 'Panadería La Esquina']);
    $other = Business::factory()->create(['name' => 'Laboratorio Vida']);
    ComplianceEvent::factory()->create(['business_id' => $business->id, 'occurred_at' => now()->subDays(3)]);
    ComplianceEvent::factory()->create(['business_id' => $other->id, 'occurred_at' => now()->subDays(200)]);
    $token = activityToken();

    $data = activityCall($token, 'compliance')->assertOk()->json('data');
    expect($data['rows'])->toHaveCount(2)
        ->and($data['count'])->toBe(trans_choice('compliance.count', 2, ['count' => 2]))
        ->and(collect($data['filters'])->pluck('value')->all())->toBe(['', ComplianceEvent::SUSPENDED, ComplianceEvent::DEBT, 'recent']);

    expect(activityCall($token, 'compliance?search=esquina')->json('data.rows.0.name'))->toBe('Panadería La Esquina')
        ->and(activityCall($token, 'compliance?filter=recent')->json('data.rows'))->toHaveCount(1)
        ->and(activityCall($token, 'compliance?search=nadie')->json('data.empty.title'))->toBe(__('compliance.no_match'));

    $history = activityCall($token, "compliance/{$business->id}")->assertOk()->json('data');
    expect($history['name'])->toBe('Panadería La Esquina')
        ->and($history['events'])->toHaveCount(1)
        ->and($history['subtitle'])->toBe(trans_choice('compliance.panel.subtitle', 1, ['count' => 1]));

    activityCall($token, 'compliance/999999')->assertNotFound();
});

test('compliance loads to the end as the web does, with no page cap', function (): void {
    foreach (range(1, 30) as $index) {
        ComplianceEvent::factory()->create(['business_id' => Business::factory()->create()->id]);
    }
    $token = activityToken();

    expect(activityCall($token, 'compliance')->json('data.has_more'))->toBeTrue()
        ->and(activityCall($token, 'compliance?page=2')->json('data.rows'))->toHaveCount(30)
        ->and(activityCall($token, 'compliance?page=2')->json('data.has_more'))->toBeFalse()
        ->and(activityCall($token, 'compliance?page=999')->json('data.rows'))->toHaveCount(30);
});

test('a query value sent as a list is ignored, never a 500', function (): void {
    $token = activityToken();

    activityCall($token, 'adoption?view[]=a')->assertOk()->assertJsonPath('data.view', 'stalled');
    activityCall($token, 'compliance?search[]=a&page[]=2')->assertOk();
});

test('the business button is offered only to whoever may open Negocios', function (): void {
    $business = Business::factory()->create();
    ComplianceEvent::factory()->create(['business_id' => $business->id]);
    Role::findOrCreate('auditor', 'web')->syncPermissions(['access-admin-panel', 'compliance.view']);

    expect(activityCall(activityToken('auditor'), 'compliance')->json('data.rows.0.target'))->toBeNull();
});

test('whoever may open Negocios gets the business button', function (): void {
    ComplianceEvent::factory()->create(['business_id' => Business::factory()->create()->id]);

    expect(activityCall(activityToken(), 'compliance')->json('data.rows.0.target.screen'))->toBe('admin.businesses');
});

test('with an empty base each screen answers its own empty words, never a 500', function (): void {
    $token = activityToken();

    expect(activityCall($token, 'adoption')->assertOk()->json('data.empty.title'))->toBe(__('adoption.empty_title'))
        ->and(activityCall($token, 'incidents')->assertOk()->json('data.empty.title'))->toBe(__('incidents.all_good_title'))
        ->and(activityCall($token, 'contacts')->assertOk()->json('data.empty.title'))->toBe(__('contacts.empty_title'))
        ->and(activityCall($token, 'compliance')->assertOk()->json('data.empty.title'))->toBe(__('compliance.empty_title'));
});

test('each screen asks for the key of its web screen', function (string $path, string $key): void {
    Role::findOrCreate('other', 'web')->syncPermissions(['access-admin-panel', 'alerts.view']);
    $token = activityToken('other');

    activityCall($token, $path)->assertForbidden();

    Role::findByName('other', 'web')->givePermissionTo($key);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    activityCall($token, $path)->assertOk();
})->with([
    ['adoption', 'adoption.view'],
    ['incidents', 'incidents.view'],
    ['contacts', 'contacts.view'],
    ['compliance', 'compliance.view'],
]);

test('one business\'s history asks for the key of Cumplimiento too', function (): void {
    $business = Business::factory()->create();
    ComplianceEvent::factory()->create(['business_id' => $business->id]);
    Role::findOrCreate('other', 'web')->syncPermissions(['access-admin-panel', 'alerts.view']);
    $token = activityToken('other');

    activityCall($token, 'compliance/'.$business->id)->assertForbidden();

    Role::findByName('other', 'web')->givePermissionTo('compliance.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    activityCall($token, 'compliance/'.$business->id)->assertOk()->assertJsonPath('data.name', $business->name);
});

test('a customer\'s token reads none of the four screens', function (string $path): void {
    test()->withToken(appInstallToken(payingUser()))->withHeaders(apiDeviceHeaders())->getJson('/api/v1/admin/'.$path)
        ->assertForbidden()->assertJsonPath('code', 'staff_only');
})->with(['adoption', 'incidents', 'contacts', 'compliance']);

test('a suspended business comes first even when its last event is older than a whole page', function (): void {
    $suspended = Business::factory()->create(['name' => 'ZZ Suspendido', 'suspended_at' => now()]);
    ComplianceEvent::factory()->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDays(300)]);
    Business::factory()->count(30)->create()->each(
        fn (Business $business) => ComplianceEvent::factory()->create(['business_id' => $business->id, 'occurred_at' => now()->subDay()]),
    );

    expect(activityCall(activityToken(), 'compliance')->json('data.rows.0.name'))->toBe('ZZ Suspendido');
});

test('an alarm about a suspended business leads to Cumplimiento with that business', function (): void {
    $this->seed(MenuSeeder::class);
    $business = Business::factory()->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessSuspended)->create(['about_business_id' => $business->id]);

    expect(activityCall(activityToken(), 'alerts')->json('data.events.rows.0.target'))->toBe(['screen' => 'admin.compliance', 'query' => ['negocio' => $business->id]]);
});

test('an alarm leads nowhere for whoever may not open Cumplimiento', function (): void {
    $this->seed(MenuSeeder::class);
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessSuspended)->create(['about_business_id' => Business::factory()->create()->id]);
    Role::findOrCreate('other', 'web')->syncPermissions(['access-admin-panel', 'alerts.view']);

    $rows = activityCall(activityToken('other'), 'alerts')->assertOk()->json('data.events.rows');

    expect($rows)->toHaveCount(1)->and($rows[0]['target'])->toBeNull();
});
