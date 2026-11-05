<?php

declare(strict_types=1);

use App\Actions\Support\CreateSupportTicket;
use App\Models\Business;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);

    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
    Mail::fake();
    Http::fake();

    $this->business = Business::factory()->create();
});

function supportStaff(): User
{
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('support');

    return $staff->refresh();
}

test('a screenshot is kept on the private disk and never on the public one', function (): void {
    $user = User::factory()->create(['business_id' => test()->business->id]);

    $ticket = app(CreateSupportTicket::class)->handle(
        $user,
        'No me carga la pantalla de citas.',
        SupportTicketKind::query()->where('code', 'problem')->firstOrFail(),
        'agenda',
        [],
        UploadedFile::fake()->image('captura.png'),
    );

    expect($ticket->attachment_path)->toStartWith('businesses/'.test()->business->id.'/support/');

    Storage::disk('local')->assertExists($ticket->attachment_path);
    Storage::disk('public')->assertMissing($ticket->attachment_path);
});

test('support staff open the screenshot through the protected route', function (): void {
    $path = 'businesses/'.test()->business->id.'/support/captura.png';
    Storage::disk('local')->put($path, 'png-bytes');
    $ticket = SupportTicket::factory()->create(['business_id' => test()->business->id, 'attachment_path' => $path]);

    $response = $this->actingAs(supportStaff())->get(route('admin.support.attachment', $ticket));

    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('png-bytes');
});

test('a business user and a visitor cannot open it', function (): void {
    $path = 'businesses/'.test()->business->id.'/support/captura.png';
    Storage::disk('local')->put($path, 'png-bytes');
    $ticket = SupportTicket::factory()->create(['business_id' => test()->business->id, 'attachment_path' => $path]);

    $owner = User::factory()->create(['business_id' => test()->business->id, 'email_verified_at' => now()]);
    $owner->assignRole('client');

    $this->actingAs($owner)->get(route('admin.support.attachment', $ticket))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.support.attachment', $ticket))->assertRedirect();
});

test('a ticket without a screenshot answers not found', function (): void {
    $ticket = SupportTicket::factory()->create(['business_id' => test()->business->id, 'attachment_path' => null]);

    $this->actingAs(supportStaff())->get(route('admin.support.attachment', $ticket))->assertNotFound();
});
