<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTeamMember
{
    /** @param  array{whatsapp: ?string, department_ids: list<int>}  $data  Already validated. */
    public function handle(Business $business, User $member, array $data): User
    {
        // All or nothing: a failed sync must not leave the new number saved beside the old departments.
        DB::transaction(function () use ($business, $member, $data): void {
            $member->forceFill(['whatsapp' => $data['whatsapp']])->save();
            $member->departments()->sync($business->departments()->whereKey($data['department_ids'])->pluck('departments.id'));
        });

        return $member;
    }
}
