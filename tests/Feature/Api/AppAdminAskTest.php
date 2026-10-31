<?php

declare(strict_types=1);

use App\Ai\Agents\AskAdmin;
use App\Enums\PlatformEventSeverity;
use App\Mail\DeviceChallengeCode;
use App\Models\AiUsage;
use App\Models\PlatformEvent;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The admin's "Asistente IA" from the app
|--------------------------------------------------------------------------
| The web panel's own piece (`AdminAskDesk`): its words, its daily cap and
| the SDK's stored conversation, joined only when it is this admin's.
| The model is faked: nothing in this file spends a token.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);
});

function adminAskToken(string $role = 'admin'): string
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

/** @param  array<string, mixed>  $body */
function adminAskCall(string $token, string $method, string $path, array $body = []): TestResponse
{
    // Several people in one test: the guard would keep the first one it resolved.
    app('auth')->forgetGuards();

    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->json($method, '/api/v1/'.$path, $body);
}

function spendAdminQuestions(int $count): void
{
    foreach (range(1, $count) as $ignored) {
        AiUsage::query()->create(['model' => 'gpt-6-luna', 'input_tokens' => 0, 'cached_tokens' => 0, 'output_tokens' => 0, 'kind' => AiUsage::ASK_ADMIN]);
    }
}

describe('who gets it', function (): void {
    test('the key holder gets the tab and the sheet; support, without the key, gets neither', function (): void {
        $admin = adminAskToken();
        $support = adminAskToken('support');

        expect(adminAskCall($admin, 'GET', 'me')->json('data.ask'))->toBe(['tab' => __('ask_admin.button')])
            ->and(adminAskCall($support, 'GET', 'me')->json('data.ask'))->toBeNull();

        adminAskCall($admin, 'GET', 'admin/ask')->assertOk();
        adminAskCall($support, 'GET', 'admin/ask')->assertForbidden();
        adminAskCall($support, 'POST', 'admin/ask', ['question' => 'hola'])->assertForbidden();
    });

    test('a customer token never reaches it', function (): void {
        AskAdmin::fake()->preventStrayPrompts();
        $token = appInstallToken(payingUser());

        adminAskCall($token, 'POST', 'admin/ask', ['question' => 'hola'])->assertForbidden()->assertJsonPath('code', 'staff_only');
        AskAdmin::assertNeverPrompted();
    });
});

describe('the sheet', function (): void {
    test('it carries the web panel\'s words, its suggestions, the day card and the cap left', function (): void {
        PlatformEvent::factory()->severity(PlatformEventSeverity::Critical)->create();
        config()->set('atendia.admin_assistant.daily_questions', 10);
        spendAdminQuestions(3);

        $sheet = adminAskCall(adminAskToken(), 'GET', 'admin/ask')->assertOk()->json('data');

        expect($sheet['title'])->toBe(__('ask_admin.title'))
            ->and($sheet['subtitle'])->toBe(__('ask_admin.subtitle'))
            ->and($sheet['placeholder'])->toBe(__('ask_admin.placeholder'))
            ->and($sheet['new_chat'])->toBe(__('ask_admin.new_chat'))
            ->and(array_column($sheet['suggestions'], 'question'))->toBe([
                __('ask_admin.suggestions.pulse'), __('ask_admin.suggestions.alerts'), __('ask_admin.suggestions.payments'), __('ask_admin.suggestions.trouble'),
            ])
            ->and($sheet['quota'])->toBe(['left' => 7, 'line' => trans_choice('ask_admin.left', 7, ['count' => 7])])
            ->and($sheet['used_up'])->toBeNull();

        $alerts = collect($sheet['today']['rows'])->firstWhere('key', 'alerts');
        expect($alerts['label'])->toBe(trans_choice('ask_admin.today.alerts', 1, ['count' => 1]))
            ->and($alerts['target'])->toBe(['screen' => 'admin.alerts', 'query' => []]);
    });

    test('with the cap reached the box gives way to the web\'s sentence', function (): void {
        config()->set('atendia.admin_assistant.daily_questions', 2);
        spendAdminQuestions(2);

        expect(adminAskCall(adminAskToken(), 'GET', 'admin/ask')->json('data.used_up'))->toBe(__('ask_admin.used_up', ['cap' => 2]));
    });
});

describe('asking', function (): void {
    test('an answer comes with its stored conversation, and the next question continues it', function (): void {
        AskAdmin::fake(['Hay 3 negocios.', 'Dos pagan tarde.']);
        $token = adminAskToken();

        $first = adminAskCall($token, 'POST', 'admin/ask', ['question' => '  ¿Cuántos negocios hay?  '])->assertOk()->json('data');

        expect($first['answer']['text'])->toBe('Hay 3 negocios.')
            ->and($first['failed'])->toBeNull()
            ->and($first['conversation'])->toBeString();
        AskAdmin::assertPrompted('¿Cuántos negocios hay?');

        $second = adminAskCall($token, 'POST', 'admin/ask', ['question' => '¿Y cuáles pagan tarde?', 'conversation' => $first['conversation']])->assertOk()->json('data');

        expect($second['conversation'])->toBe($first['conversation'])
            ->and(DB::table('agent_conversations')->count())->toBe(1)
            ->and(DB::table('agent_conversation_messages')->where('conversation_id', $first['conversation'])->count())->toBe(4);
    });

    test('a conversation id of another admin is not joined', function (): void {
        AskAdmin::fake(['A.', 'B.']);

        $theirs = adminAskCall(adminAskToken(), 'POST', 'admin/ask', ['question' => 'dato de la otra'])->json('data.conversation');
        $mine = adminAskCall(adminAskToken(), 'POST', 'admin/ask', ['question' => 'intento entrar', 'conversation' => $theirs])->json('data.conversation');

        expect($mine)->not->toBe($theirs)
            ->and(DB::table('agent_conversation_messages')->where('conversation_id', $theirs)->count())->toBe(2);
    });

    test('a link to a screen the app has becomes a door; any other stays words', function (): void {
        $business = route('admin.businesses', ['negocio' => 5]);
        $mentions = route('admin.mentions');
        AskAdmin::fake(["Mira [Ferretería]({$business}) y [las menciones]({$mentions})."]);

        $parts = collect(adminAskCall(adminAskToken(), 'POST', 'admin/ask', ['question' => '¿quién debe?'])->json('data.answer.parts'));

        expect($parts->firstWhere('text', 'Ferretería'))->toBe(['kind' => 'link', 'text' => 'Ferretería', 'target' => ['screen' => 'admin.businesses', 'query' => ['negocio' => '5']]])
            ->and($parts->firstWhere('text', 'las menciones'))->toBe(['kind' => 'text', 'text' => 'las menciones']);
    });

    test('a model that fails answers with the web\'s notice and keeps the conversation it had', function (): void {
        AskAdmin::fake([fn () => throw new RuntimeException('provider down')]);
        $kept = (string) Str::uuid7();

        $reply = adminAskCall(adminAskToken(), 'POST', 'admin/ask', ['question' => 'hola', 'conversation' => $kept])->assertOk()->json('data');

        expect($reply['answer'])->toBeNull()
            ->and($reply['failed'])->toBe(__('ask_admin.failed'))
            ->and($reply['conversation'])->toBe($kept);
    });

    test('with the cap reached it refuses with the web\'s sentence and never reaches the model', function (): void {
        AskAdmin::fake()->preventStrayPrompts();
        config()->set('atendia.admin_assistant.daily_questions', 1);
        spendAdminQuestions(1);

        adminAskCall(adminAskToken(), 'POST', 'admin/ask', ['question' => 'una más'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'ask_used_up')
            ->assertJsonPath('message', __('ask_admin.used_up', ['cap' => 1]));
        AskAdmin::assertNeverPrompted();
    });

    test('the box\'s own rules: no empty question, no novel, no made-up conversation id', function (array $body, string $field): void {
        AskAdmin::fake()->preventStrayPrompts();

        adminAskCall(adminAskToken(), 'POST', 'admin/ask', $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        AskAdmin::assertNeverPrompted();
    })->with([
        'blank' => [['question' => '   '], 'question'],
        'too long' => [['question' => str_repeat('a', 501)], 'question'],
        'not a list of words' => [['question' => ['hola']], 'question'],
        'forged id' => [['question' => 'hola', 'conversation' => '1 OR 1=1'], 'conversation'],
    ]);
});

describe('the Preguntar buttons', function (): void {
    test('the home and every alert carry the web\'s question for the key holder, and nothing for support', function (): void {
        $event = PlatformEvent::factory()->severity(PlatformEventSeverity::Critical)->create();
        $admin = adminAskToken();

        expect(adminAskCall($admin, 'GET', 'admin/home')->json('data.ask'))->toBe(['label' => __('ask_admin.ask_button'), 'question' => __('ask_admin.ask.pulse')]);

        $alerts = adminAskCall($admin, 'GET', 'admin/alerts')->json('data.events');
        $row = collect($alerts['rows'])->firstWhere('id', $event->id);
        expect($alerts['ask'])->toBe(__('ask_admin.ask_button'))
            ->and($row['question'])->toBe(__('ask_admin.ask.alert', ['kind' => $row['title'], 'business' => $row['business']]));

        $support = adminAskToken('support');
        expect(adminAskCall($support, 'GET', 'admin/home')->json('data.ask'))->toBeNull();
    });
});
