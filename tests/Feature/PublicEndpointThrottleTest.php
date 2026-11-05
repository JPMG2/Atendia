<?php

declare(strict_types=1);

test('the reset-link request is capped per client so one inbox cannot be flooded', function (): void {
    foreach (range(1, 6) as $attempt) {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(302);
    }

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);
});

test('the referral landing is capped so codes cannot be enumerated at speed', function (): void {
    foreach (range(1, 30) as $attempt) {
        $this->get('/r/NOSUCHCODE')->assertRedirect(route('register'));
    }

    $this->get('/r/NOSUCHCODE')->assertStatus(429);
});
