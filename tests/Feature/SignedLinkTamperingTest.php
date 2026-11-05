<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottleByAddressBucket;
use App\Models\LoginDevice;
use App\Models\User;
use App\Services\SignedLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The links mailed to a person act with no login: they must not bend
|--------------------------------------------------------------------------
| Restore an account, confirm or cancel an e-mail change, verify an address and
| revoke a device all open from a mail. For each one, the genuine link must work
| (or the test proves nothing), and every way of bending it must answer 403 and
| change nothing: a forged signature, none at all, a longer life, another
| person's id and a link past its time.
*/

/**
 * One fresh account in the state its link acts on, the maker of that link, and the way to tell whether it worked.
 *
 * @return array{link: Closure(DateTimeInterface): string, swap: array{0: int, 1: int}, worked: Closure(): bool}
 */
function signedCase(string $name): array
{
    $user = payingUser();
    $other = payingUser();
    $new = 'new-'.Str::lower(Str::random(8)).'@shop.test';

    if ($name === 'confirm the new address') {
        $user->forceFill(['pending_email' => $new])->save();

        return [
            'link' => fn (DateTimeInterface $expires): string => SignedLink::temporary('settings.email.confirm', $expires, ['user' => $user->id, 'hash' => sha1($new)]),
            'swap' => [$user->id, $other->id],
            'worked' => fn (): bool => $user->refresh()->email === $new,
        ];
    }

    if ($name === 'cancel the new address') {
        $user->forceFill(['pending_email' => $new])->save();

        return [
            'link' => fn (DateTimeInterface $expires): string => SignedLink::temporary('settings.email.cancel', $expires, ['user' => $user->id, 'hash' => sha1($new)]),
            'swap' => [$user->id, $other->id],
            'worked' => fn (): bool => $user->refresh()->pending_email === null,
        ];
    }

    if ($name === 'verify the address') {
        $user->forceFill(['email_verified_at' => null])->save();

        return [
            'link' => fn (DateTimeInterface $expires): string => SignedLink::temporary('settings.email.verify', $expires, ['user' => $user->id, 'hash' => sha1((string) $user->email)]),
            'swap' => [$user->id, $other->id],
            'worked' => fn (): bool => $user->refresh()->email_verified_at !== null,
        ];
    }

    if ($name === 'restore the account') {
        $user->delete();

        return [
            'link' => fn (DateTimeInterface $expires): string => SignedLink::temporary('account.restore', $expires, ['user' => $user->id]),
            'swap' => [$user->id, $other->id],
            'worked' => fn (): bool => ! User::withTrashed()->find($user->id)->trashed(),
        ];
    }

    $device = LoginDevice::factory()->for($user)->create();
    $strangers = LoginDevice::factory()->for($other)->create();

    return [
        'link' => fn (DateTimeInterface $expires): string => SignedLink::temporary('security.devices.revoke', $expires, ['device' => $device->id]),
        'swap' => [$device->id, $strangers->id],
        // A revoked device is gone; the other person's device must survive any attempt.
        'worked' => fn (): bool => ! LoginDevice::query()->whereKey($device->id)->exists() || ! LoginDevice::query()->whereKey($strangers->id)->exists(),
    ];
}

/** @param  array{0: int, 1: int}  $swap */
function bentLink(string $url, string $how, array $swap): string
{
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    $path = $parts['path'];

    if ($how === 'a forged signature') {
        $query['signature'] = strrev((string) $query['signature']);
    }

    if ($how === 'no signature') {
        unset($query['signature']);
    }

    if ($how === 'a longer life') {
        $query['expires'] = (int) $query['expires'] + 31536000;
    }

    if ($how === "someone else's id") {
        $path = (string) preg_replace('#/'.$swap[0].'(?=/|$)#', '/'.$swap[1], $path, 1);
    }

    return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path.'?'.http_build_query($query);
}

test('every mailed link works as itself and does nothing when bent', function (): void {
    // The throttle is its own test: here a 429 would hide the 403 this sweep is after.
    $this->withoutMiddleware(ThrottleByAddressBucket::class);

    $names = ['confirm the new address', 'cancel the new address', 'verify the address', 'restore the account', 'revoke the device'];
    $problems = [];

    foreach ($names as $name) {
        $genuine = signedCase($name);
        $this->get(($genuine['link'])(now()->addDay()))->assertSuccessful();

        if (! ($genuine['worked'])()) {
            $problems[] = "{$name}: the genuine link did nothing, so the sweep would prove nothing";
        }

        foreach (['a forged signature', 'no signature', 'a longer life', "someone else's id", 'a time already past'] as $how) {
            $case = signedCase($name);
            $url = $how === 'a time already past'
                ? ($case['link'])(now()->subMinute())
                : bentLink(($case['link'])(now()->addDay()), $how, $case['swap']);

            $status = $this->get($url)->status();

            if ($status !== 403 || ($case['worked'])()) {
                $problems[] = "{$name} / {$how}: answered {$status}".(($case['worked'])() ? ' AND IT TOOK EFFECT' : '');
            }
        }
    }

    expect($problems)->toBe([]);
});
