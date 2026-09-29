<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Mail\TeamInvitationMail;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Offers a seat by email. The token travels only in the mail; the row keeps
 * its hash, so the link is the one key and it expires on its own.
 */
class InviteTeamMember
{
    /** @param  array{name: string, email: string, whatsapp: ?string, department_ids: list<int>}  $data  Already validated. */
    public function handle(Business $business, array $data, User $inviter): TeamInvitation
    {
        // The plan's seats are people: the door is here, not in hiding the button.
        throw_unless($business->canOfferSeatTo($data['email']), RuntimeException::class, 'The plan seats are full.');

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

        return $invitation;
    }
}
