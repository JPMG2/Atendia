<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TeamInvitation;

class CancelTeamInvitation
{
    public function handle(TeamInvitation $invitation): void
    {
        $invitation->delete();
    }
}
