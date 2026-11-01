<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('a throttled app call is told to wait in the app language, not the framework english', function (): void {
    $call = fn () => $this->withHeaders(apiDeviceHeaders())->postJson('/api/v1/login/challenge', ['challenge_id' => str_repeat('a', 48), 'code' => '123456']);

    foreach (range(1, 10) as $try) {
        $call();
    }

    $call()->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertExactJson(['message' => __('errors.too_many_requests.message'), 'code' => 'too_many_requests']);
});

test('a server failure reaches the app as our own sentence and leaks nothing', function (): void {
    config(['app.debug' => false]);
    Route::get('api/v1/test-boom', fn () => throw new RuntimeException('secret detail'));

    $this->getJson('/api/v1/test-boom')
        ->assertStatus(500)
        ->assertExactJson(['message' => __('errors.server_error.message'), 'code' => 'server_error']);
});

test('the web keeps its own error pages untouched', function (): void {
    config(['app.debug' => false]);
    Route::get('test-boom-web', fn () => throw new RuntimeException('secret detail'));

    $this->get('/test-boom-web')
        ->assertStatus(500)
        ->assertSee(__('errors.server_error.title'));
});
