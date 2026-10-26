<?php

declare(strict_types=1);

use App\Enums\MessageKind;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

/** Calls the API as a paying business's person, from an install the account already used. */
function asAppUser(User $user): TestCase
{
    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function teamAgentOf(User $owner, array $departments = []): User
{
    $agent = payingUser();
    $agent->forceFill(['business_id' => $owner->business_id])->save();
    $agent->syncRoles(['agent']);
    $agent->departments()->sync(collect($departments)->pluck('id'));

    return $agent->refresh();
}

describe('menu', function (): void {
    it('is the web menu in the web order, cut down to the screens the app has', function (): void {
        $owner = payingUser();

        asAppUser($owner)->getJson('/api/v1/menu')
            ->assertOk()
            ->assertJsonPath('data.*.label', ['Mi negocio', 'Catálogo', 'Conversaciones', 'Clientes', 'Agenda', 'WhatsApp', 'Plan y pagos'])
            ->assertJsonPath('data.0.screen', 'my-business')
            // A group has no screen of its own: "Servicios" and "Productos" are the catalog's two children the app can draw.
            ->assertJsonPath('data.1.screen', null)
            ->assertJsonPath('data.1.children.*.screen', ['my-services', 'my-products'])
            ->assertJsonPath('data.2.screen', 'conversations')
            ->assertJsonPath('data.2.icon', 'message-circle')
            ->assertJsonPath('data.3.screen', 'customers')
            ->assertJsonPath('data.4.screen', 'agenda')
            ->assertJsonPath('data.6.screen', null)
            ->assertJsonPath('data.6.children.*.screen', ['my-plan']);
    });

    it('keeps the web nesting and order as the app grows a screen', function (): void {
        config(['atendia.app.screens' => ['conversations', 'my-services', 'my-products']]);
        $owner = payingUser();

        $response = asAppUser($owner)->getJson('/api/v1/menu')->assertOk();

        expect($response->json('data.0.children.*.screen'))->toBe(['my-services', 'my-products'])
            ->and($response->json('data.*.label'))->toBe([__('menu.catalog'), 'Conversaciones']);
    });

    it('does not draw what the person may not open on the web', function (): void {
        $owner = payingUser();
        $agent = teamAgentOf($owner);

        asAppUser($agent)->getJson('/api/v1/menu')->assertOk()->assertJsonPath('data.*.label', ['Conversaciones', 'Clientes']);
    });

    it('speaks the language the phone announces', function (): void {
        $owner = payingUser();

        asAppUser($owner)->withHeader('Accept-Language', 'en')->getJson('/api/v1/menu')->assertJsonPath('data.2.label', 'Conversaciones');
    });
});

describe('conversations', function (): void {
    it('lists the newest first with a short preview and never another business', function (): void {
        $owner = payingUser();
        $older = Conversation::factory()->create(['business_id' => $owner->business_id, 'last_message_at' => now()->subHour(), 'contact_name' => 'Ana']);
        $newer = Conversation::factory()->create(['business_id' => $owner->business_id, 'last_message_at' => now(), 'contact_name' => 'Beto']);
        ConversationMessage::factory()->out()->create(['business_id' => $owner->business_id, 'conversation_id' => $newer->id, 'body' => str_repeat('palabra ', 60)]);
        Conversation::factory()->create(['contact_name' => 'Ajena']);

        $response = asAppUser($owner)->getJson('/api/v1/conversations')->assertOk();

        expect($response->json('data.*.name'))->toBe(['Beto', 'Ana'])
            ->and(mb_strlen($response->json('data.0.last_message.preview')))->toBeLessThanOrEqual(140)
            ->and($response->json('data.0.last_message.direction'))->toBe('out')
            ->and($response->json('data.0'))->not->toHaveKey('messages')
            ->and($older->id)->not->toBe($newer->id);
    });

    it('walks the inbox in pages, never all at once', function (): void {
        config(['atendia.app.conversations_per_page' => 2]);
        $owner = payingUser();

        foreach (range(1, 5) as $minutes) {
            Conversation::factory()->create(['business_id' => $owner->business_id, 'last_message_at' => now()->subMinutes($minutes)]);
        }

        $first = asAppUser($owner)->getJson('/api/v1/conversations')->assertOk();
        expect($first->json('data'))->toHaveCount(2)->and($first->json('links.next'))->not->toBeNull();

        $second = asAppUser($owner)->getJson($first->json('links.next'))->assertOk();
        expect($second->json('data'))->toHaveCount(2)
            ->and(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
    });

    it('refuses a made-up cursor instead of sending it to the database', function (string $made): void {
        $owner = payingUser();
        Conversation::factory()->create(['business_id' => $owner->business_id]);
        $cursor = rtrim(strtr(base64_encode($made), '+/', '-_'), '=');

        asAppUser($owner)->getJson("/api/v1/conversations?cursor={$cursor}")->assertUnprocessable()->assertJsonValidationErrors('cursor');
    })->with([
        'a date that is not one' => ['{"last_message_at":"garbage","id":1,"_pointsToNextItems":true}'],
        'a list instead of a date' => ['{"last_message_at":[1],"id":1,"_pointsToNextItems":true}'],
        'an id that is text' => ['{"last_message_at":"2026-10-21 10:00:00","id":"1 or 1=1","_pointsToNextItems":true}'],
        'no keys the list uses' => ['{"x":1,"_pointsToNextItems":true}'],
        'not even a cursor' => ['not-a-cursor'],
    ]);

    it('opens a thread with its messages oldest to newest and a way back to older ones', function (): void {
        config(['atendia.app.messages_per_page' => 3]);
        $owner = payingUser();
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id]);
        $messages = ConversationMessage::factory()->count(5)->create(['business_id' => $owner->business_id, 'conversation_id' => $thread->id]);

        $latest = asAppUser($owner)->getJson("/api/v1/conversations/{$thread->id}")->assertOk();
        $ids = $latest->json('data.messages.*.id');

        expect($ids)->toBe($messages->slice(2)->pluck('id')->all())->and($latest->json('meta.has_older'))->toBeTrue();

        $older = asAppUser($owner)->getJson("/api/v1/conversations/{$thread->id}?before={$ids[0]}")->assertOk();

        expect($older->json('data.messages.*.id'))->toBe($messages->slice(0, 2)->pluck('id')->all())
            ->and($older->json('meta.has_older'))->toBeFalse();
    });

    it('marks an internal note as one, so the app never shows it as the customer’s words', function (): void {
        $owner = payingUser();
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id]);
        ConversationMessage::factory()->create(['business_id' => $owner->business_id, 'conversation_id' => $thread->id, 'kind' => MessageKind::Note, 'body' => 'Llamarlo mañana']);

        asAppUser($owner)->getJson("/api/v1/conversations/{$thread->id}")->assertJsonPath('data.messages.0.kind', 'note');
    });

    it('does not open the thread of another business', function (): void {
        $owner = payingUser();
        $foreign = Conversation::factory()->create();

        asAppUser($owner)->getJson("/api/v1/conversations/{$foreign->id}")->assertNotFound();
    });

    it('shows an agent only the threads of their departments', function (): void {
        $owner = payingUser();
        [$sales, $payments] = Department::factory()->count(2)->sequence(['name' => 'Ventas'], ['name' => 'Pagos'])->create(['business_id' => $owner->business_id]);
        $agent = teamAgentOf($owner, [$sales]);
        $mine = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $sales->id]);
        $notMine = Conversation::factory()->create(['business_id' => $owner->business_id, 'department_id' => $payments->id]);

        asAppUser($agent)->getJson('/api/v1/conversations')->assertJsonPath('data.*.id', [$mine->id]);
        asAppUser($agent)->getJson("/api/v1/conversations/{$notMine->id}")->assertNotFound();
    });

    it('cannot change a thread: WhatsApp is where the conversation lives', function (string $method): void {
        $owner = payingUser();
        $thread = Conversation::factory()->create(['business_id' => $owner->business_id]);

        asAppUser($owner)->json($method, "/api/v1/conversations/{$thread->id}", ['body' => 'hola'])->assertStatus(405);
        asAppUser($owner)->json($method, '/api/v1/conversations', ['body' => 'hola'])->assertStatus(405);

        expect(ConversationMessage::query()->where('conversation_id', $thread->id)->count())->toBe(0);
    })->with(['POST', 'PUT', 'PATCH', 'DELETE']);

    it('stays closed to a business that has not paid', function (): void {
        $trial = Business::factory()->create();
        $user = User::factory()->create(['business_id' => $trial->id]);

        asAppUser($user)->getJson('/api/v1/conversations')->assertForbidden()->assertJsonPath('reason', 'on_trial');
    });
});

describe('whatsapp light', function (): void {
    it('is green only when the bridge confirmed the link lately', function (): void {
        $owner = payingUser();
        $owner->business->forceFill(['whatsapp_number' => '5491122334455', 'whatsapp_connected_at' => now()->subDay()])->save();
        Cache::put('wa:verified:'.$owner->business_id, true, now()->addMinutes(5));

        asAppUser($owner)->getJson('/api/v1/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.state', 'connected')
            ->assertJsonPath('data.number', '5491122334455');
    });

    it('is amber when the bridge did not answer, never a green nobody confirmed', function (): void {
        $owner = payingUser();
        $owner->business->forceFill(['whatsapp_connected_at' => now()->subDay()])->save();
        Cache::forget('wa:verified:'.$owner->business_id);

        asAppUser($owner)->getJson('/api/v1/whatsapp')->assertJsonPath('data.state', 'unverified');
    });

    it('is red when the link is not there', function (): void {
        $owner = payingUser();

        asAppUser($owner)->getJson('/api/v1/whatsapp')->assertJsonPath('data.state', 'disconnected');
    });

    it('is for whoever manages the business, not for an agent', function (): void {
        $owner = payingUser();

        asAppUser(teamAgentOf($owner))->getJson('/api/v1/whatsapp')->assertForbidden();
    });
});
