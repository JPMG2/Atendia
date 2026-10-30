<?php

declare(strict_types=1);

use App\Ai\Agents\AskAtendia;
use App\Classes\Main\AnswerHtml;
use App\Models\AiUsage;
use App\Models\AskFeedback;
use App\Models\User;
use Database\Seeders\AssistantSkillSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(DatabaseTransactions::class);

/*
|--------------------------------------------------------------------------
| "Pregúntale" in the app
|--------------------------------------------------------------------------
| The web panel's own piece (AskDesk): its words, its quota and its agent.
| The thread lives on the phone; only the owner of a plan that opens it asks.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(AssistantSkillSeeder::class);
});

/** One call as this person; Sanctum's guard remembers the first person it saw. */
function asAskApp(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(appInstallToken($user))->withHeaders(apiDeviceHeaders());
}

function askOwner(string $plan = 'negocio'): User
{
    $owner = payingUser(['name' => 'Carla Ruiz']);
    $owner->syncRoles(['client']);
    $owner->business->subscription->forceFill(['plan' => $plan])->save();

    return $owner->refresh();
}

function spendAppQuestions(User $owner, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        AiUsage::query()->create(['business_id' => $owner->business_id, 'kind' => AiUsage::ASK_ATENDIA, 'model' => 'm']);
    }
}

test('the sheet brings the web panel\'s words, suggestions and the quota left', function (): void {
    $owner = askOwner();
    spendAppQuestions($owner, 13);

    $data = asAskApp($owner)->getJson('/api/v1/ask')->assertOk()->json('data');

    expect($data['title'])->toBe(__('ask.title'))
        ->and($data['hello'])->toBe(__('ask.hello', ['name' => 'Carla']))
        ->and(collect($data['intro'])->pluck('kind')->all())->toBe(['text', 'brand', 'pill', 'brand', 'text'])
        ->and(collect($data['intro'])->pluck('text')->implode(''))->toBe(__('ask.intro', ['assistant' => __('ask.assistant', ['ia' => __('ask.ia')])]))
        ->and($data['suggestions'])->toHaveCount(4)
        ->and($data['today']['rows'])->toBe([])
        ->and($data['today']['clear'])->toBe(__('ask.today.clear'))
        ->and($data['quota'])->toMatchArray(['left' => 87, 'cap' => 100, 'after' => __('ask.quota.of_short', ['cap' => 100])])
        ->and($data['used_up'])->toBeNull();
});

test('/me opens the bar\'s last tab for the owner of a plan that has it, and not on the floor plan', function (): void {
    // Every business before the first call: the call leaves the database speaking as its tenant.
    [$owner, $floor] = [askOwner(), askOwner('emprende')];

    expect(asAskApp($owner)->getJson('/api/v1/me')->json('data.ask'))->toBe(['tab' => __('ask.tab')])
        ->and(asAskApp($floor)->getJson('/api/v1/me')->json('data.ask'))->toBeNull();
});

test('a question comes back in pieces with its id, and the next one carries it as the thread', function (): void {
    AskAtendia::fake(['Ayer, **2 conversaciones**.', 'Hoy, 3.']);
    $owner = askOwner();

    $first = asAskApp($owner)->postJson('/api/v1/ask', ['question' => '  ¿Qué consultaron ayer?  '])->assertOk()->json('data');
    asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Y hoy?', 'thread' => [$first['answer']['id']]])->assertOk();

    expect($first['answer']['parts'])->toBe([
        ['kind' => 'text', 'text' => 'Ayer, '],
        ['kind' => 'bold', 'text' => '2 conversaciones'],
        ['kind' => 'text', 'text' => '.'],
    ])->and($first['failed'])->toBeNull();

    // The model hears the real question and the real answer, never words the phone typed in.
    AskAtendia::assertPrompted(fn ($prompt): bool => $prompt->prompt === '¿Y hoy?'
        && array_map(fn ($message): ?string => $message->content, $prompt->agent->messages()) === ['¿Qué consultaron ayer?', 'Ayer, **2 conversaciones**.']);
});

test('a long session is never refused: the thread keeps the last five answers and ignores what it never gave', function (): void {
    AskAtendia::fake(array_fill(0, 7, 'Listo.'));
    [$owner, $other] = [askOwner(), askOwner()];
    $foreign = asAskApp($other)->postJson('/api/v1/ask', ['question' => '¿De otro?'])->json('data.answer.id');
    $ids = [];

    foreach (range(1, 6) as $round) {
        $ids[] = asAskApp($owner)->postJson('/api/v1/ask', ['question' => "Pregunta {$round}", 'thread' => [...$ids, $foreign, ...array_fill(0, 30, fake()->uuid())]])->assertOk()->json('data.answer.id');
    }

    AskAtendia::assertPrompted(fn ($prompt): bool => $prompt->prompt === 'Pregunta 6'
        && count($prompt->agent->messages()) === 10
        && $prompt->agent->messages()[0]->content === 'Pregunta 1'
        && ! in_array('¿De otro?', array_map(fn ($message): ?string => $message->content, $prompt->agent->messages()), true));
});

test('a bold run that holds a link keeps both', function (): void {
    expect(AnswerHtml::parts('**Revisa [la charla]('.route('conversations', ['hilo' => 3]).') ya** y [**Agenda**]('.route('agenda').').'))->toBe([
        ['kind' => 'bold', 'text' => 'Revisa '],
        ['kind' => 'link', 'text' => 'la charla', 'url' => route('conversations', ['hilo' => 3])],
        ['kind' => 'bold', 'text' => ' ya'],
        ['kind' => 'text', 'text' => ' y '],
        ['kind' => 'link', 'text' => 'Agenda', 'url' => route('agenda')],
        ['kind' => 'text', 'text' => '.'],
    ]);
});

test('a second question while the first is being answered waits, and never reaches the agent', function (): void {
    AskAtendia::fake();
    $owner = askOwner();
    $held = Cache::lock('ask-busy:'.$owner->business_id, 60);
    $held->get();

    asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Hola?'])->assertStatus(409)->assertJsonPath('message', __('ask.busy'));

    $held->release();
    AskAtendia::assertNeverPrompted();
});

test('a link to a screen the app has becomes a door, any other stays words', function (): void {
    AskAtendia::fake(['Mira [la charla]('.route('conversations', ['hilo' => 42]).'), [Estadísticas]('.route('statistics').') y [esto](https://evil.test/x).']);

    $parts = asAskApp(askOwner())->postJson('/api/v1/ask', ['question' => '¿Qué pasó?'])->assertOk()->json('data.answer.parts');

    expect($parts[1])->toBe(['kind' => 'link', 'text' => 'la charla', 'target' => ['screen' => 'conversations', 'query' => ['hilo' => '42']]])
        ->and($parts[3])->toBe(['kind' => 'text', 'text' => 'Estadísticas'])
        ->and($parts[5])->toBe(['kind' => 'text', 'text' => 'esto']);
});

test('an answer is rated once, as on the web', function (): void {
    AskAtendia::fake(['Nada pendiente.']);
    $owner = askOwner();
    $id = asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Algo pendiente?'])->json('data.answer.id');

    asAskApp($owner)->postJson('/api/v1/ask/rate', ['id' => $id, 'rating' => AskFeedback::UP])->assertOk()->assertJsonPath('message', __('ask.rate.thanks'));
    asAskApp($owner)->postJson('/api/v1/ask/rate', ['id' => $id, 'rating' => AskFeedback::DOWN])->assertNotFound();

    expect(AskFeedback::query()->where('business_id', $owner->business_id)->pluck('rating')->all())->toBe([AskFeedback::UP]);
});

test('another business cannot rate an answer it never got', function (): void {
    AskAtendia::fake(['Nada pendiente.']);
    [$owner, $other] = [askOwner(), askOwner()];
    $id = asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Algo pendiente?'])->json('data.answer.id');

    asAskApp($other)->postJson('/api/v1/ask/rate', ['id' => $id, 'rating' => AskFeedback::UP])->assertNotFound();
});

test('a spent quota answers with the web\'s sentence and never reaches the agent', function (): void {
    AskAtendia::fake();
    $owner = askOwner();
    spendAppQuestions($owner, 100);

    asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Qué consultaron hoy?'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'ask_used_up')
        ->assertJsonPath('message', __('ask.used_up', ['cap' => 100, 'date' => $owner->business->quotaRenewsOn()->format('d/m/Y')]));

    AskAtendia::assertNeverPrompted();
});

test('an empty question never reaches the agent', function (): void {
    AskAtendia::fake();

    asAskApp(askOwner())->postJson('/api/v1/ask', ['question' => '   '])->assertUnprocessable()->assertJsonValidationErrors('question');

    AskAtendia::assertNeverPrompted();
});

test('a failed call says the web\'s sentence and spends nothing', function (): void {
    AskAtendia::fake(fn () => throw new RuntimeException('provider down'));

    $owner = askOwner();
    $data = asAskApp($owner)->postJson('/api/v1/ask', ['question' => '¿Qué consultaron hoy?'])->assertOk()->json('data');

    expect($data['answer'])->toBeNull()
        ->and($data['failed'])->toBe(__('ask.failed'))
        ->and($data['quota']['left'])->toBe(100)
        ->and(AiUsage::query()->where('business_id', $owner->business_id)->count())->toBe(0);
});

test('the floor plan, an agent and a customer-less token are refused', function (): void {
    AskAtendia::fake();
    $owner = askOwner();
    $agent = payingUser();
    $agent->forceFill(['business_id' => $owner->business_id])->save();
    $agent->syncRoles(['agent']);
    $floor = askOwner('emprende');

    asAskApp($floor)->getJson('/api/v1/ask')->assertForbidden();
    asAskApp($floor)->postJson('/api/v1/ask', ['question' => '¿Hola?'])->assertForbidden();
    asAskApp($agent->refresh())->postJson('/api/v1/ask', ['question' => '¿Hola?'])->assertForbidden();
    app('auth')->forgetGuards();
    test()->flushHeaders()->getJson('/api/v1/ask')->assertUnauthorized();

    AskAtendia::assertNeverPrompted();
});
