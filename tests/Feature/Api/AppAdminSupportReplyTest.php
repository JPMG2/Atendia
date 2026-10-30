<?php

declare(strict_types=1);

use App\Enums\SupportDelivery;
use App\Enums\SupportTicketStatus;
use App\Mail\DeviceChallengeCode;
use App\Models\Business;
use App\Models\HelpArticle;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\EvolutionApi;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Answering a report from the app
|--------------------------------------------------------------------------
| The phone's box is the web's: its words, its rules, and the same Action,
| which says by which road the answer left, or that it did not.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('services.evolution.instance', 'atendia-demo');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->business = Business::factory()->create(['fallback_whatsapp_number' => '+584140001122', 'billing_email' => 'duena@clinica.test']);
    $this->whatsapp = mock(EvolutionApi::class);
    app()->instance(EvolutionApi::class, $this->whatsapp);
});

function replyToken(string $role = 'admin'): string
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

function replyCall(string $token, string $method, string $path, array $body = []): TestResponse
{
    return test()->withToken($token)->withHeaders(apiDeviceHeaders())->json($method, '/api/v1/admin/support/'.$path, $body);
}

test('an open report brings the web\'s answer box: its words and what each article and saved reply pastes', function (): void {
    $ticket = SupportTicket::factory()->for($this->business)->create();
    SupportReply::factory()->create(['name' => 'Reconectar', 'body' => 'Abre WhatsApp y vuelve a vincular.', 'is_active' => true]);
    HelpArticle::factory()->create(['title' => 'No sale el QR', 'is_active' => true]);

    $composer = replyCall(replyToken(), 'GET', (string) $ticket->id)->assertOk()->json('data.composer');

    expect($composer['label'])->toBe(__('support.admin.reply'))
        ->and($composer['max'])->toBe(900)
        ->and($composer['send'])->toBe(__('support.admin.send_reply'))
        ->and(collect($composer['saved']['options'])->firstWhere('label', 'Reconectar')['text'])->toBe('Abre WhatsApp y vuelve a vincular.')
        ->and(collect($composer['article']['options'])->firstWhere('label', 'No sale el QR')['text'])->toContain('«No sale el QR»');
});

test('a settled report has no box, and answering or resolving it is refused', function (): void {
    $ticket = SupportTicket::factory()->for($this->business)->create(['status' => SupportTicketStatus::Resolved, 'resolved_at' => now()]);
    $token = replyToken();

    expect(replyCall($token, 'GET', (string) $ticket->id)->assertOk()->json('data.composer'))->toBeNull();
    replyCall($token, 'POST', "{$ticket->id}/reply", ['body' => 'Hola'])->assertStatus(409);
    replyCall($token, 'POST', "{$ticket->id}/resolve")->assertStatus(409);
});

test('an answer goes through the web\'s Action and says by which road it left', function (): void {
    $this->whatsapp->shouldReceive('sendText')->once()->andReturnTrue();
    $ticket = SupportTicket::factory()->for($this->business)->create(['status' => SupportTicketStatus::New]);

    replyCall(replyToken(), 'POST', "{$ticket->id}/reply", ['body' => '  Revisa los dispositivos vinculados.  '])
        ->assertOk()
        ->assertJson(['reached' => true, 'message' => SupportDelivery::WhatsApp->outcome()]);

    expect($ticket->messages()->latest('id')->first()->body)->toBe('Revisa los dispositivos vinculados.');
});

test('a saved reply is read as it is now when picked, and a switched-off one pastes nothing', function (): void {
    $reply = SupportReply::factory()->create(['body' => 'Texto viejo', 'is_active' => true]);
    $token = replyToken();

    $reply->update(['body' => 'Texto nuevo']);
    replyCall($token, 'GET', "saved-replies/{$reply->id}")->assertOk()->assertJsonPath('data.text', 'Texto nuevo');

    $reply->update(['is_active' => false]);
    replyCall($token, 'GET', "saved-replies/{$reply->id}")->assertOk()->assertJsonPath('data.text', null);
});

test('the same answer sent again after a lost response is not sent twice', function (): void {
    $this->whatsapp->shouldReceive('sendText')->once()->andReturnTrue();
    $ticket = SupportTicket::factory()->for($this->business)->create(['status' => SupportTicketStatus::New]);
    $token = replyToken();
    $body = ['body' => 'Revisa los dispositivos vinculados.', 'request_id' => 'a1b2c3d4-e5f6'];

    $first = replyCall($token, 'POST', "{$ticket->id}/reply", $body)->assertOk()->json();
    $again = replyCall($token, 'POST', "{$ticket->id}/reply", $body)->assertOk()->json();

    expect($again)->toBe($first)->and($ticket->messages()->count())->toBe(1);
});

test('an answer that reached nobody says so and is not an answered report', function (): void {
    $this->business->update(['fallback_whatsapp_number' => null, 'billing_email' => '']);
    $ticket = SupportTicket::factory()->for($this->business)->create(['user_id' => null, 'status' => SupportTicketStatus::New]);

    replyCall(replyToken(), 'POST', "{$ticket->id}/reply", ['body' => 'Respuesta que no sale'])
        ->assertOk()
        ->assertJson(['reached' => false, 'message' => SupportDelivery::NoContact->outcome()]);

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::New);
});

test('the answer keeps the web\'s rules, and resolving rings as on the web', function (): void {
    $ticket = SupportTicket::factory()->for($this->business)->create();
    $token = replyToken();

    replyCall($token, 'POST', "{$ticket->id}/reply", ['body' => ' '])->assertStatus(422)->assertJsonValidationErrors('body');
    replyCall($token, 'POST', "{$ticket->id}/reply", ['body' => str_repeat('a', 901)])->assertStatus(422)->assertJsonValidationErrors('body');
    replyCall($token, 'POST', "{$ticket->id}/reply", ['body' => ['x']])->assertStatus(422);

    replyCall($token, 'POST', "{$ticket->id}/resolve")->assertOk()->assertJson(['message' => __('support.admin.saved')]);
    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::Resolved);
});

test('a staff role without the support key cannot answer', function (): void {
    $ticket = SupportTicket::factory()->for($this->business)->create();
    $role = Role::findOrCreate('auditor', 'web');
    $role->syncPermissions(['access-admin-panel']);

    replyCall(replyToken('auditor'), 'POST', "{$ticket->id}/reply", ['body' => 'Hola'])->assertForbidden();
});
