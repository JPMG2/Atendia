<?php

declare(strict_types=1);

use App\Enums\MailSender;
use App\Mail\BillingReminder;
use App\Mail\BusinessSuspended;
use App\Mail\NewCompany;
use App\Messaging\Channels\Email;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['mail.from.address' => 'no-reply@aliadito.test']);
});

test('every mailable of the app is assigned to exactly one sender', function (): void {
    $classes = collect(glob(app_path('Mail/*.php')))
        ->map(fn (string $file): string => 'App\\Mail\\'.basename($file, '.php'));

    // A new mail that nobody assigned would go out as no-reply by default, and a
    // payment notice nobody can answer is exactly what this map prevents.
    $unassigned = $classes->reject(fn (string $class): bool => collect(MailSender::cases())
        ->filter(fn (MailSender $sender): bool => in_array($class, $sender->mailables(), true))
        ->count() === 1);

    expect($unassigned->values()->all())->toBe([]);
});

test('every sender address lives on the domain the app sends from', function (): void {
    foreach (MailSender::cases() as $sender) {
        expect($sender->address())->toEndWith('@aliadito.test');
    }

    expect(MailSender::Billing->address())->toBe('cobranzas@aliadito.test')
        ->and(MailSender::System->address())->toBe('no-reply@aliadito.test');
});

test('the channel stamps each mail with the sender of its kind', function (string $mailable, string $address): void {
    Mail::fake();

    (new Email(Company::factory()->create(), ['someone@shop.test'], $mailable))->send();

    // Read off the property: hasFrom() falls through to the envelope, which has no from of its own.
    Mail::assertQueued($mailable, fn ($mail): bool => $mail->hasTo('someone@shop.test')
        && ($mail->from[0]['address'] ?? null) === $address);
})->with([
    'security and platform mail is no-reply' => [NewCompany::class, 'no-reply@aliadito.test'],
    'a payment notice comes from billing' => [BillingReminder::class, 'cobranzas@aliadito.test'],
    'a suspension comes from support, where its reply lands' => [BusinessSuspended::class, 'soporte@aliadito.test'],
]);

test('the name next to the address carries the brand and the area', function (): void {
    expect(MailSender::Billing->displayName('es'))->toContain(Company::brand())->toContain('Cobranzas')
        ->and(MailSender::System->displayName('es'))->toBe(Company::brand());
});
