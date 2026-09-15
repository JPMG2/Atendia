<?php

declare(strict_types=1);

test('registration is rate limited to slow down bot signups', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post('/register', [])->assertStatus(302);
    }

    $this->post('/register', [])->assertStatus(429);
});
