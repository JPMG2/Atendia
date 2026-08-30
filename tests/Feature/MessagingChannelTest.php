<?php

declare(strict_types=1);

use App\Mail\NewCompany;
use App\Messaging\Channel;
use App\Messaging\Channels\Email;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** A channel that records what the ritual handed it instead of sending anything. */
class SpyChannel extends Channel
{
    public static ?string $locale = null;

    protected function deliver(string $locale): void
    {
        self::$locale = $locale;
    }
}

/** A channel that fails the way a real one does when its service is down. */
class BrokenChannel extends Channel
{
    protected function deliver(string $locale): void
    {
        throw new RuntimeException('the service is down');
    }
}

beforeEach(function (): void {
    SpyChannel::$locale = null;
});

test('the channel hands the delivery the locale of the request', function (): void {
    $company = Company::factory()->create();

    // Captured here and not in the worker: a queued message is built where there
    // is no session, so the regional variant the person picked would be lost.
    App::setLocale('es_AR');

    (new SpyChannel($company, ['hola@atendia.app'], NewCompany::class))->send();

    expect(SpyChannel::$locale)->toBe('es_AR');
});

test('a channel that blows up does not blow up its caller', function (): void {
    Exceptions::fake();

    $company = Company::factory()->create();

    // The company was already saved by the time the message goes out: a service
    // that is down cannot turn that save into an error.
    (new BrokenChannel($company, ['hola@atendia.app'], NewCompany::class))->send();

    Exceptions::assertReported(RuntimeException::class);
});

test('the delivery is protected, so nobody can skip the ritual', function (): void {
    $deliver = new ReflectionMethod(Channel::class, 'deliver');

    // A public deliver() would let a caller send the message straight, skipping
    // the locale and the error handling the ritual adds.
    expect($deliver->isProtected())->toBeTrue()
        ->and($deliver->isAbstract())->toBeTrue();
});

test('the channel keeps a single abstract method, so a new channel is a subclass', function (): void {
    $abstract = array_filter(
        (new ReflectionClass(Channel::class))->getMethods(),
        fn (ReflectionMethod $method): bool => $method->isAbstract(),
    );

    // The day this grows to two, every channel has to implement a method meant
    // for another one — and the empty implementation drops messages in silence.
    expect($abstract)->toHaveCount(1);
});

test('a message class that does not exist is rejected on the spot', function (): void {
    $company = Company::factory()->create();

    // A typo used to survive until somebody saved the form in production, and by
    // then the try/catch of send() swallowed it.
    expect(fn () => new Email($company, ['hola@atendia.app'], 'App\\Mail\\NoExiste'))
        ->toThrow(InvalidArgumentException::class, 'no existe la clase de mensaje');
});

test('the email channel refuses a message that is not a mailable', function (): void {
    $company = Company::factory()->create();

    // Company::class builds fine on its own: without the guard the failure would
    // surface inside Mail, far from the line that caused it.
    expect(fn () => new Email($company, ['hola@atendia.app'], Company::class))
        ->toThrow(InvalidArgumentException::class, 'tiene que ser un');
});

test('a channel with no contract takes any class that exists', function (): void {
    $company = Company::factory()->create();

    // The base class only guarantees the class is there: what a message has to
    // BE is each channel's business, and a channel may not care.
    (new SpyChannel($company, ['hola@atendia.app'], Company::class))->send();

    expect(SpyChannel::$locale)->not->toBeNull();
});

test('the email channel builds the message and queues it for its recipients', function (): void {
    Mail::fake();

    $company = Company::factory()->create();

    (new Email($company, ['hola@atendia.app'], NewCompany::class))->send();

    Mail::assertQueued(NewCompany::class, function (NewCompany $mail) use ($company): bool {
        return $mail->hasTo('hola@atendia.app')
            && $mail->model->is($company);
    });
});
