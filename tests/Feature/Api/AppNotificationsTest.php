<?php

declare(strict_types=1);

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** One call as this person, from the install the account already used. Sanctum's guard remembers the first person it saw, so each call forgets it. */
function asBellApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

/** A second person of the same business, with their own install. */
function teammateOf(User $owner): User
{
    $teammate = payingUser();
    $teammate->forceFill(['business_id' => $owner->business_id])->save();

    return $teammate->refresh();
}

describe('the feed', function (): void {
    it('is the web bell: the same words, cut in day groups, with what is unread', function (): void {
        $user = payingUser();
        PanelNotification::factory()->for($user->business)->create([
            'type' => PanelNotificationType::WhatsAppDisconnected,
            'dedupe_key' => 'wa:1',
            'payload' => ['at' => '09:41'],
        ]);

        $data = asBellApp($user)->getJson('/api/v1/notifications')->assertOk()->json('data');

        expect($data['unread'])->toBe(1)
            ->and($data['digest'])->toContain('1 corte de WhatsApp')
            ->and($data['groups'])->toHaveCount(1)
            ->and($data['groups'][0]['group'])->toBe('today')
            ->and($data['groups'][0]['label'])->toBe('Hoy')
            ->and($data['groups'][0]['rows'][0])->toMatchArray([
                'type' => 'whatsapp_disconnected',
                'text' => 'Tu WhatsApp se desconectó a las 09:41 y dejó de responder',
                'read' => false,
            ]);
    });

    it('bundles three of a kind into one counted row that carries every id', function (): void {
        $user = payingUser();
        $ids = PanelNotification::factory()->count(4)->for($user->business)->create()->pluck('id')->all();

        $rows = asBellApp($user)->getJson('/api/v1/notifications')->assertOk()->json('data.groups.0.rows');

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['text'])->toBe('4 clientes esperan respuesta')
            ->and($rows[0]['ids'])->toEqualCanonicalizing($ids);
    });

    it('says its empty state with the web\'s words in a Venezuelan visit', function (): void {
        asBellApp(payingUser())->withHeaders(['Accept-Language' => 'es-US', 'CF-IPCountry' => 'VE'])->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.empty.title', 'No hay nada pendiente')
            ->assertJsonPath('data.empty.body', 'Acá aparece lo que necesita tu atención: un cliente esperando respuesta, una cita nueva o tu WhatsApp caído.');
    });

    it('says its empty state in the neutral words when the visit has no regional variant', function (): void {
        asBellApp(payingUser())->withHeaders(['Accept-Language' => 'es-US'])->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.empty.body', 'Acá aparece lo que necesita tu atención: un cliente esperando respuesta, un turno nuevo o tu WhatsApp caído.');
    });

    it('is empty and dark when there is nothing to read', function (): void {
        asBellApp(payingUser())->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.unread', 0)
            ->assertJsonPath('data.digest', '')
            ->assertJsonPath('data.groups', []);
    });

    it('hides the kinds this person muted, and only for them', function (): void {
        $owner = payingUser();
        $teammate = teammateOf($owner);
        PanelNotification::factory()->for($owner->business)->create();
        $teammate->toggleBellType(PanelNotificationType::CustomerWaiting, false);

        asBellApp($teammate)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.unread', 0);
        asBellApp($owner)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.unread', 1);
    });

    it('never shows another business notices', function (): void {
        $user = payingUser();
        PanelNotification::factory()->for(Business::factory()->create())->create();

        asBellApp($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.unread', 0);
    });
});

describe('reading', function (): void {
    it('marks what was opened as read for this person only', function (): void {
        $owner = payingUser();
        $teammate = teammateOf($owner);
        $notice = PanelNotification::factory()->for($owner->business)->create();

        asBellApp($teammate)->postJson('/api/v1/notifications/read', ['ids' => [$notice->id]])
            ->assertOk()->assertJsonPath('data.unread', 0);

        asBellApp($owner)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.unread', 1);
    });

    it('marks every notice of a bundled row at once', function (): void {
        $user = payingUser();
        $ids = PanelNotification::factory()->count(4)->for($user->business)->create()->pluck('id')->all();

        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => $ids])->assertOk()->assertJsonPath('data.unread', 0);
    });

    it('marks everything read in one go', function (): void {
        $user = payingUser();
        PanelNotification::factory()->count(5)->for($user->business)->create();

        asBellApp($user)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.unread', 0);
    });

    it('cannot mark another business notice, even knowing its id', function (): void {
        $user = payingUser();
        $theirs = PanelNotification::factory()->for(Business::factory()->create())->create();

        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => [$theirs->id]])->assertOk();

        expect($theirs->readers()->count())->toBe(0);
    });

    it('refuses an empty, oversized or malformed list of ids', function (): void {
        $user = payingUser();

        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => []])->assertUnprocessable();
        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => range(1, 201)])->assertUnprocessable();
        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => ['abc', [1]]])->assertUnprocessable();
        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => '5'])->assertUnprocessable();
        asBellApp($user)->postJson('/api/v1/notifications/read', ['ids' => [99999999999999999999]])->assertUnprocessable();
    });

    it('needs a token', function (): void {
        test()->withHeaders(apiDeviceHeaders())->getJson('/api/v1/notifications')->assertUnauthorized();
    });
});
