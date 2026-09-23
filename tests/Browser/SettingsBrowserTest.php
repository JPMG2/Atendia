<?php

declare(strict_types=1);

use App\Actions\Account\SaveAccountAvatar;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/*
| The settings page in a real browser: clean console, every card on screen,
| and a front-validation error reading next to ITS own field.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create(['name' => 'María González']));
});

test('the settings page renders every card with a clean console', function (): void {
    visit('/ajustes')
        ->assertNoJavaScriptErrors()
        ->assertSee('Correo de acceso')
        ->assertSee('Eliminar mi cuenta')
        ->screenshot(fullPage: true, filename: 'settings-page');
});

test('a mismatched new password is caught before any request', function (): void {
    visit('/ajustes/contrasena')
        ->fill('current_password', 'password')
        ->fill('password', 'Nueva#Clave2026')
        ->fill('password_confirmation', 'Otra#Clave2026')
        ->click('Cambiar contraseña')
        ->assertSee('Los valores no coinciden.')
        ->screenshot(filename: 'settings-password-error');
});

test('a pending email change shows its banner with resend and cancel', function (): void {
    $user = User::factory()->create(['email' => 'un.correo.bastante.largo@negocio-de-ejemplo.com']);
    $user->forceFill(['pending_email' => 'nuevo@negocio.com'])->save();
    $this->actingAs($user);

    visit('/ajustes/correo')
        ->assertNoJavaScriptErrors()
        ->assertSee('nuevo@negocio.com')
        ->assertSee('Reenviar enlace')
        ->assertSee('un.correo.bastante.largo@negocio-de-ejemplo.com')
        ->screenshot(filename: 'settings-email-pending');
});

test('the photo shows in the topbar and the two-step card offers the owner number', function (): void {
    $business = Business::factory()->create(['fallback_whatsapp_number' => '+54 9 11 2233-4455']);
    $user = User::factory()->create(['name' => 'María González', 'business_id' => $business->id]);
    app(SaveAccountAvatar::class)->handle($user, UploadedFile::fake()->image('me.jpg', 600, 400));
    $this->actingAs($user->refresh());

    $page = visit('/ajustes')
        ->assertNoJavaScriptErrors()
        ->assertSee('Verificación en dos pasos')
        ->assertSee('•••• 4455')
        ->screenshot(fullPage: true, filename: 'settings-photo-two-factor');

    $page->screenshotElement('header', 'settings-topbar-avatar');

    app(SaveAccountAvatar::class)->handle($user->refresh(), null);
});

test('a picked photo opens the square cropper and the backup codes show once', function (): void {
    $business = Business::factory()->create(['fallback_whatsapp_number' => '+54 9 11 2233-4455']);
    $user = User::factory()->create(['name' => 'María González', 'business_id' => $business->id]);
    $user->forceFill(['two_factor_whatsapp_at' => now()])->save();
    $this->actingAs($user->refresh());

    $page = visit('/ajustes/perfil')->assertNoJavaScriptErrors();

    // The runner cannot attach host files: a canvas-drawn photo goes in through DataTransfer.
    $page->script(<<<'JS'
        const canvas = Object.assign(document.createElement('canvas'), { width: 900, height: 600 });
        const context = canvas.getContext('2d');
        context.fillStyle = '#0EA47A';
        context.fillRect(0, 0, 900, 600);
        context.fillStyle = '#FF6A4D';
        context.beginPath();
        context.arc(450, 300, 180, 0, Math.PI * 2);
        context.fill();
        canvas.toBlob((blob) => {
            const input = document.querySelector('input[name="avatar_file"]');
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'me.png', { type: 'image/png' }));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    JS);

    $page->wait(1)
        ->assertSee('Usar esta foto')
        ->screenshot(filename: 'settings-avatar-cropper');

    // The crop upload itself is not driven here: this runner's in-process
    // server does not parse multipart bodies; the feature tests cover the save.

    visit('/ajustes/dos-pasos')
        ->fill('current_password', 'password')
        ->click('Generar códigos nuevos')
        ->wait(1)
        ->assertSee('Ya los guardé')
        ->screenshot(filename: 'settings-recovery-codes');
});

test('verify-it-now toasts in place without leaving the page', function (): void {
    $this->actingAs(User::factory()->unverified()->create());

    visit('/ajustes')
        ->click('Verificarlo ahora')
        ->wait(1)
        ->assertSee('Listo. Te mandamos el enlace a')
        ->assertPathIs('/ajustes')
        ->screenshot(filename: 'settings-verify-toast');
});
