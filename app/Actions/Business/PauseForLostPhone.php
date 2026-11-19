<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Actions\Account\ChangeAccountPassword;
use App\Actions\Admin\RaisePlatformEvent;
use App\Actions\Admin\ResolvePlatformEvent;
use App\Enums\PlatformEventKind;
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
    /** How far back a browser sign-in still counts as an access worth listing. */
    public const int RECENT_DAYS = 30;

    public function __construct(
        private readonly UnlinkBusinessWhatsApp $unlink,
        private readonly ChangeAccountPassword $changePassword,
    ) {}

    /**
     * @param  string|null  $keepFingerprint  The device pausing: its row is marked, never dropped (see cutSessions).
     * @return bool Whether the bridge confirmed the unlink; the pause holds either way.
     */
    public function handle(Business $business, User $by, string $newPassword, ?string $keepFingerprint = null): bool
    {
        if ($business->isPausedForLostPhone()) {
            return true;
        }

        $business->forceFill([
            'lost_phone_paused_at' => now(),
            'lost_phone_paused_by' => $by->id,
            'lost_phone_cut_sessions' => $this->cutSessions($business, $by, $keepFingerprint),
        ])->save();

        $this->changePassword->handle($by, $newPassword, false, '');
        $this->endEveryOtherAccess($business);

        app(RaisePlatformEvent::class)->handle(PlatformEventKind::LostPhonePaused, $business->id);
        // One fact, one line: a "disconnected" one asking to rescan would contradict the pause.
        app(ResolvePlatformEvent::class)->ofKind(PlatformEventKind::WhatsAppDisconnected, $business->id);

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
     * What is about to lose its access, read before the app rows are deleted:
     * the owner checks it for a device she does not know. App installs with a
     * live token always count; a browser only if it signed in lately. Nothing
     * is left out by fingerprint: a stolen phone of the same model shares the
     * pausing one's, so that row is only marked as "this device or one alike".
     *
     * @return list<array{name: string, device: string, app: bool, mobile: bool, current: bool, last_login_at: string|null}>
     */
    private function cutSessions(Business $business, User $by, ?string $keepFingerprint): array
    {
        $cut = [];
        $since = now()->subDays(self::RECENT_DAYS);

        foreach ($business->users()->get() as $member) {
            $installs = $member->tokens()->pluck('name')->all();

            foreach ($member->recentDevices() as $device) {
                $app = in_array($device->fingerprint, $installs, true);

                if (! $app && ($device->last_login_at === null || $device->last_login_at->lt($since))) {
                    continue;
                }

                $cut[] = [
                    'name' => $member->name,
                    'device' => $device->label(),
                    'app' => $app,
                    'mobile' => $app || $device->isMobile(),
                    'current' => $member->is($by) && $keepFingerprint !== null && $device->fingerprint === $keepFingerprint,
                    'last_login_at' => $device->last_login_at?->toIso8601String(),
                ];
            }
        }

        return $cut;
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
