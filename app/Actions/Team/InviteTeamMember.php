<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Mail\TeamInvitationMail;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Offers a seat by email. The token travels only in the mail; the row keeps
 * its hash, so the link is the one key and it expires on its own.
 */
class InviteTeamMember
{
    /** Mails one business may send to one address in a day: the brand's name must not flood a stranger's inbox. */
    public const int MAILS_PER_DAY = 5;

    /** A second tap inside this many seconds is the same invitation, not a new mail. */
    public const int SAME_TAP_SECONDS = 60;

    /** The same for "Reenviar": short, because resending a minute later is on purpose. */
    public const int SAME_RESEND_SECONDS = 10;

    /** @param  array{name: string, email: string, whatsapp: ?string, department_ids: list<int>}  $data  Already validated. */
    public function handle(Business $business, array $data, User $inviter, bool $resend = false): TeamInvitation
    {
        // The plan's seats are people: the door is here, not in hiding the button.
        throw_unless($business->canOfferSeatTo($data['email']), RuntimeException::class, 'The plan seats are full.');

        // A resend is asked for on purpose ("it never arrived"), so its window is a double tap's only:
        // a second mail would also kill the first one's link.
        $sent = $business->teamInvitations()->where('email', $data['email'])->first();
        $window = $resend ? self::SAME_RESEND_SECONDS : self::SAME_TAP_SECONDS;

        if ($sent !== null && $sent->updated_at->greaterThan(now()->subSeconds($window))) {
            return $sent;
        }

        throw_if(self::mailsLeft($business, $data['email']) === 0, RuntimeException::class, 'Too many invitations to this address today.');

        $token = Str::random(48);

        $invitation = $business->teamInvitations()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] !== '' ? $data['name'] : null,
                'whatsapp' => $data['whatsapp'],
                'department_ids' => $data['department_ids'],
                'token_hash' => TeamInvitation::hashToken($token),
                'expires_at' => now()->addDays((int) config('atendia.team.invitation_days')),
                'invited_by' => $inviter->id,
            ],
        );

        (new Email($invitation, [$invitation->email], TeamInvitationMail::class, [$token]))->send();
        RateLimiter::hit(self::mailKey($business, $data['email']), 86400);

        return $invitation;
    }

    /** How many more invitation mails this business may send to this address today. */
    public static function mailsLeft(Business $business, string $email): int
    {
        return RateLimiter::remaining(self::mailKey($business, $email), self::MAILS_PER_DAY);
    }

    private static function mailKey(Business $business, string $email): string
    {
        return 'team-invite:'.$business->id.':'.Str::lower(trim($email));
    }
}
