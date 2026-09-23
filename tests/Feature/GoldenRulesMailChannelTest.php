<?php

declare(strict_types=1);

use App\Mail\AccountEmailVerification;
use App\Mail\AccountPasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Golden rule: every mail leaves through the messaging channel
|--------------------------------------------------------------------------
| One door, one ritual (locale capture + report): App\Messaging\Channels\
| Email. A raw Mail:: call skips both and was how DeviceChallenge drifted
| (caught in the 2026-09-19 consistency audit). Mirrored by the hook
| check-mail-channel-golden-rules.sh — touch one, touch the other.
*/

test('no app code outside the messaging layer touches the Mail facade', function (): void {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (str_starts_with($relative, 'Messaging'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $contents = $file->getContents();

        if (preg_match('/\bMail::/', $contents) === 1 || str_contains($contents, 'Facades\Mail')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});

test('no app code sends mail through Laravel notifications either', function (): void {
    // ->notify() and Notification:: are a second, ritual-less mail door: the
    // stock password-reset and verification mails leaked out that way.
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        $contents = $file->getContents();

        if (preg_match('/->notify\(|\bNotification::|Facades\\Notification/', $contents) === 1) {
            $offenders[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});

test('the stock password-reset and verification mails leave through the channel', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('token');
    $user->sendEmailVerificationNotification();

    Mail::assertQueued(AccountPasswordReset::class);
    Mail::assertQueued(AccountEmailVerification::class);
});
