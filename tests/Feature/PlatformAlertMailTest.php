<?php

declare(strict_types=1);

use App\Actions\Admin\NotifyPlatformAdmin;
use App\Enums\PlatformEventKind;
use App\Mail\PlatformAlertMail;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A critical also reaches the admin's inbox
|--------------------------------------------------------------------------
| The alert used to ride only on the platform's WhatsApp line, so the day that
| line fell the alert about it never arrived: three days unnoticed. The e-mail
| does not wait for the send window and does not need a phone number.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Mail::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['email' => 'duena@aliadito.test', 'whatsapp' => null]);
    $this->admin->assignRole('admin');
});

test('a critical is e-mailed to the admin once, with no phone number on file', function (): void {
    $event = PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();

    app(NotifyPlatformAdmin::class)->handle();
    app(NotifyPlatformAdmin::class)->handle();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(PlatformAlertMail::class, fn (PlatformAlertMail $mail): bool => $mail->hasTo('duena@aliadito.test') && $mail->model->is($event));
    expect($event->refresh()->emailed_at)->not->toBeNull();
});

test('it does not wait for the send window: a critical at three in the morning is e-mailed now', function (): void {
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 03:00', Company::localTimezone()));

    app(NotifyPlatformAdmin::class)->handle();

    Mail::assertQueuedCount(1);
});

test('only a critical interrupts the inbox: what is merely important waits on the board', function (): void {
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentLate)->create();

    app(NotifyPlatformAdmin::class)->handle();

    Mail::assertNothingQueued();
});

test('only the admins are written to, never support staff', function (): void {
    $support = User::factory()->create(['email' => 'soporte@aliadito.test']);
    $support->assignRole('support');
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();

    app(NotifyPlatformAdmin::class)->handle();

    Mail::assertQueued(PlatformAlertMail::class, fn (PlatformAlertMail $mail): bool => $mail->hasTo('duena@aliadito.test') && ! $mail->hasTo('soporte@aliadito.test'));
});

test('a platform that has no admin writes to nobody and does not break', function (): void {
    $this->admin->removeRole('admin');
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create();

    expect(app(NotifyPlatformAdmin::class)->handle())->toBe(0);

    Mail::assertNothingQueued();
});

test('the mail says what happened, who it is about and where to act', function (): void {
    $event = PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create(['detail' => 'negocio · USD 79,00']);

    $mail = new PlatformAlertMail($event);

    $mail->assertSeeInHtml($event->kind->label())
        ->assertSeeInHtml('negocio · USD 79,00')
        ->assertSeeInHtml(route('admin.alerts'))
        ->assertSeeInText($event->kind->label())
        ->assertHasSubject('Aviso crítico: '.$event->kind->label());
});
