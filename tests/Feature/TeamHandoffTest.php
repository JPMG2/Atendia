<?php

declare(strict_types=1);

use App\Ai\Agents\AsistenteAtendia;
use App\Ai\Tools\EscalateToHuman;
use App\Ai\Tools\OwnerTeamStatus;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('ai.providers.openai.url', 'http://openai.test/v1');
    config()->set('ai.providers.openai.key', 'test-openai');
    Cache::flush();
    Http::fake([
        'http://openai.test/*' => Http::response(['results' => [['flagged' => false]]]),
        'http://evolution.test/*' => Http::response(['key' => ['id' => 'PING-1']]),
    ]);
});

/** @return list<ClientRequest> */
function teamPings(): array
{
    return array_values(array_filter(
        array_map(fn (array $pair): ClientRequest => $pair[0], Http::recorded()->all()),
        fn (ClientRequest $request): bool => str_contains($request->url(), '/message/sendText/'),
    ));
}

/** A business on the trial plan (departments on) with one staffed room and one agent. */
function roomWithAgent(bool $available = true, ?array $hours = null): array
{
    $business = Business::factory()->create([
        'whatsapp_instance' => 'demo',
        'whatsapp_connected_at' => now(),
        'fallback_whatsapp_number' => '+54 9 299 552-9100',
        'timezone' => 'UTC',
    ]);
    $payments = Department::factory()->create(['business_id' => $business->id, 'name' => 'Pagos', 'routing_hint' => 'Reclama un cobro.', 'hours' => $hours]);
    $agent = User::factory()->create(['business_id' => $business->id, 'whatsapp' => '5491144440000', 'is_available' => $available]);
    $agent->syncRoles(['agent']);
    $agent->departments()->sync([$payments->id]);
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_name' => 'Carla', 'contact_phone' => '5491111111111']);

    return [$business, $payments, $agent, $thread];
}

test('the assistant routes to the department it names and only its available people are pinged', function (): void {
    [$business, $payments, , $thread] = roomWithAgent();

    (new EscalateToHuman($business, $thread))->handle(new Request(['reason' => 'Reclama un cobro doble.', 'department' => 'pagos']));

    expect($thread->refresh()->department_id)->toBe($payments->id)
        ->and($thread->status)->toBe(ConversationStatus::Team)
        ->and(teamPings())->toHaveCount(1)
        ->and(teamPings()[0]['number'])->toBe('5491144440000')
        ->and(teamPings()[0]['text'])->toContain('Pagos');
});

test('with nobody available in the room the owner gets the ping, as before departments', function (): void {
    [$business, , , $thread] = roomWithAgent(available: false);

    (new EscalateToHuman($business, $thread))->handle(new Request(['reason' => 'Reclamo.', 'department' => 'Pagos']));

    expect(teamPings())->toHaveCount(1)
        ->and(teamPings()[0]['number'])->toBe('5492995529100');
});

test('a department the model made up is ignored: the thread goes to the whole team', function (): void {
    [$business, , , $thread] = roomWithAgent();

    (new EscalateToHuman($business, $thread))->handle(new Request(['reason' => 'Algo.', 'department' => 'Marketing']));

    expect($thread->refresh()->department_id)->toBeNull()
        ->and(teamPings()[0]['number'])->toBe('5492995529100');
});

test('out of the department hours the model is told to say when they will answer', function (): void {
    $this->travelTo(now()->startOfWeek()->addDays(5)->setTime(12, 0)); // Saturday noon, UTC
    [$business, , , $thread] = roomWithAgent(hours: ['1' => ['09:00', '18:00']]);

    $answer = (string) (new EscalateToHuman($business, $thread))->handle(new Request(['reason' => 'Reclamo.', 'department' => 'Pagos']));

    expect($answer)->toContain('fuera de horario')->toContain('lun · 09:00–18:00');
});

test('the tool offers the business rooms as its only choices, and the briefing says when each applies', function (): void {
    [$business, , , $thread] = roomWithAgent();

    $schema = (new EscalateToHuman($business, $thread))->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('department')
        ->and((string) (new AsistenteAtendia($business, $thread))->instructions())->toContain('- Pagos: Reclama un cobro.');
});

test('an agent answering the ping from their phone reaches the customer and takes the thread', function (): void {
    [, , $agent, $thread] = roomWithAgent();
    $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now(), 'department_id' => $agent->departments->first()->id]);

    (new ProcessIncomingWhatsAppMessage('demo', '5491144440000', 'Lucía', 'Ya revisé tu cobro, te lo devolvemos hoy.', 'MSG-A1'))->handle();

    expect(teamPings()[0]['number'])->toBe('5491111111111')
        ->and(teamPings()[0]['text'])->toBe('Ya revisé tu cobro, te lo devolvemos hoy.')
        ->and($thread->refresh()->assigned_user_id)->toBe($agent->id)
        ->and(Conversation::query()->count())->toBe(1);
});

test('the owner assistant reads the team: presence, rooms and what waits', function (): void {
    [$business, , , $thread] = roomWithAgent(available: false);
    $thread->update(['status' => ConversationStatus::Team, 'department_id' => Department::query()->sole()->id]);

    $answer = (string) (new OwnerTeamStatus($business))->handle(new Request([]));

    expect($answer)->toContain('ausente')->toContain('Pagos')->toContain('1 charlas esperando');
});
