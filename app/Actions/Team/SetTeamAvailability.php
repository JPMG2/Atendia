<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\User;

class SetTeamAvailability
{
    public function handle(User $user, bool $available): User
    {
        $user->forceFill(['is_available' => $available])->save();

        return $user;
    }
}
