<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Business;
use App\Models\User;

class UpdateTeamMember
{
    /** @param  array{whatsapp: ?string, department_ids: list<int>}  $data  Already validated. */
    public function handle(Business $business, User $member, array $data): User
    {
        $member->forceFill(['whatsapp' => $data['whatsapp']])->save();
        $member->departments()->sync($business->departments()->whereKey($data['department_ids'])->pluck('departments.id'));

        return $member;
    }
}
