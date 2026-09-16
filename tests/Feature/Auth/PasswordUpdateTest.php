<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Nueva#Clave1',
                'password_confirmation' => 'Nueva#Clave1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('Nueva#Clave1', $user->refresh()->password));
    }

    public function test_updating_the_password_can_close_every_other_session(): void
    {
        // GitHub pattern: the checkbox drops every device but the one in use,
        // and LogoutRevokedDevice kills those sessions on their next request.
        $user = User::factory()->create();
        $current = LoginDevice::factory()->for($user)->create([
            'fingerprint' => LoginDevice::fingerprintFor('Symfony'),
        ]);
        LoginDevice::factory()->for($user)->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'Nueva#Clave1',
            'password_confirmation' => 'Nueva#Clave1',
            'logout_others' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame([$current->id], $user->loginDevices()->pluck('id')->all());
    }

    public function test_updating_the_password_alone_keeps_the_other_sessions(): void
    {
        $user = User::factory()->create();
        LoginDevice::factory()->for($user)->create([
            'fingerprint' => LoginDevice::fingerprintFor('Symfony'),
        ]);
        LoginDevice::factory()->for($user)->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'Nueva#Clave1',
            'password_confirmation' => 'Nueva#Clave1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $user->loginDevices()->count());
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
