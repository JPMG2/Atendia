<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TeamInvitation;
use App\Models\User;

/** A fresh link and a fresh clock: the old mail stops working the moment this one leaves. */
class ResendTeamInvitation
{
    public function handle(TeamInvitation $invitation, User $inviter): TeamInvitation
    {
        return app(InviteTeamMember::class)->handle($invitation->business, [
            'name' => (string) $invitation->name,
            'email' => $invitation->email,
            'whatsapp' => $invitation->whatsapp,
            'department_ids' => $invitation->department_ids ?? [],
        ], $inviter);
    }
}
