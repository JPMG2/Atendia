<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ends a seat without stranding a customer: the threads they held go back
 * to their department, their words stay signed in the history, and every
 * open session dies with the seat.
 */
class RemoveTeamMember
{
    public function handle(User $member): int
    {
        return DB::transaction(function () use ($member): int {
            $released = $member->business->conversations()->where('assigned_user_id', $member->id)->update(['assigned_user_id' => null]);

            $member->departments()->detach();
            $member->tokens()->delete();
            DB::table('sessions')->where('user_id', $member->id)->delete();
            $member->delete();

            return $released;
        });
    }
}
