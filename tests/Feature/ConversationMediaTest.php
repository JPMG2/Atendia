<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

/** @param  array<string, mixed>  $item */
function messageWithMedia(Business $business, array $item): ConversationMessage
{
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);
    Storage::disk('local')->put($item['path'], 'bytes');

    return $conversation->messages()->create([
        'business_id' => $business->id,
        'direction' => MessageDirection::In,
        'body' => '📷 Foto',
        'media' => [$item],
    ]);
}

function mediaOwner(Business $business): User
{
    return User::factory()->create(['business_id' => $business->id])->refresh();
}

test('the owner opens a customer photo inline, never sniffed', function (): void {
    $business = Business::factory()->create();
    $message = messageWithMedia($business, ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => "businesses/{$business->id}/conversations/1/a.jpg"]);

    $this->actingAs(mediaOwner($business))
        ->get(route('conversations.media', ['message' => $message->id, 'index' => 0]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=a.jpg');
});

test('the carousel download button saves the photo instead of opening it', function (): void {
    $business = Business::factory()->create();
    $message = messageWithMedia($business, ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => "businesses/{$business->id}/conversations/1/a.jpg"]);

    $this->actingAs(mediaOwner($business))
        ->get(route('conversations.media', ['message' => $message->id, 'index' => 0, 'download' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=a.jpg');
});

test('a document is always a download, whatever it claims to be', function (): void {
    $business = Business::factory()->create();
    $message = messageWithMedia($business, ['kind' => 'document', 'mime' => 'text/html', 'name' => 'x.html', 'path' => "businesses/{$business->id}/conversations/1/d.bin"]);

    $this->actingAs(mediaOwner($business))
        ->get(route('conversations.media', ['message' => $message->id, 'index' => 0]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Content-Disposition', 'attachment; filename=x.html');
});

test('another business never reaches the file', function (): void {
    $business = Business::factory()->create();
    $message = messageWithMedia($business, ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => "businesses/{$business->id}/conversations/1/a.jpg"]);

    $this->actingAs(mediaOwner(Business::factory()->create()))
        ->get(route('conversations.media', ['message' => $message->id, 'index' => 0]))
        ->assertNotFound();
});

test('a guest is sent to log in', function (): void {
    $business = Business::factory()->create();
    $message = messageWithMedia($business, ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => "businesses/{$business->id}/conversations/1/a.jpg"]);

    $this->get(route('conversations.media', ['message' => $message->id, 'index' => 0]))
        ->assertRedirect(route('login'));
});
