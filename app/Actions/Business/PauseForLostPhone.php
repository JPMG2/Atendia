<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Actions\Account\ChangeAccountPassword;
use App\Mail\LostPhonePauseChanged;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Me robaron el teléfono": unlike closing the account, nothing is deleted
 * and billing goes on. The stamp silences every send; a new password kills
 * the one saved on the stolen phone; every other session and app install of
 * the team dies; and the number is unlinked from the bridge.
 */
class PauseForLostPhone
{
    public function __construct(
        private readonly UnlinkBusinessWhatsApp $unlink,
        private readonly ChangeAccountPassword $changePassword,
    ) {}

    /** @return bool Whether the bridge confirmed the unlink; the pause holds either way. */
    public function handle(Business $business, User $by, string $newPassword): bool
    {
        if ($business->isPausedForLostPhone()) {
            return true;
        }

        $business->forceFill(['lost_phone_paused_at' => now(), 'lost_phone_paused_by' => $by->id])->save();

        $this->changePassword->handle($by, $newPassword, false, '');
        $this->endEveryOtherAccess($business);

        $unlinked = true;

        try {
            // Even when the stamp says "down": a dropped socket can come back on its own.
            $this->unlink->handle($business);
        } catch (Throwable $failure) {
            report($failure);
            $unlinked = false;
        }

        (new Email($by, [$by->email], LostPhonePauseChanged::class, [true]))->send();

        return $unlinked;
    }

    /**
     * Web sessions die in EndSessionsBeforeLostPhonePause; remember-me cookies
     * and app installs (their tokens, device rows and push addresses) die
     * here, for the whole team: nobody knows whose session the stolen phone kept.
     */
    private function endEveryOtherAccess(Business $business): void
    {
        foreach ($business->users()->get() as $member) {
            $installs = $member->tokens()->pluck('name');

            $member->loginDevices()
                ->where(fn ($devices) => $devices->whereIn('fingerprint', $installs)->orWhereNotNull('push_token'))
                ->delete();
            $member->revokeAllTokens();
            $member->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        }
    }
}
