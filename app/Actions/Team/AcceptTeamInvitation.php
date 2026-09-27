<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns the invitation into a seat: an agent of THAT business. The mail
 * link proved the address, so it is born verified; departments of another
 * business can never ride in, whatever the row says.
 */
class AcceptTeamInvitation
{
    /** @param  array{name: string, password: string}  $data  Already validated. */
    public function handle(TeamInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'whatsapp' => $invitation->whatsapp,
            ]);
            $user->forceFill([
                'business_id' => $invitation->business_id,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ])->save();

            $user->assignRole('agent');
            $user->departments()->sync(
                $invitation->business->departments()->whereKey($invitation->department_ids ?? [])->pluck('departments.id'),
            );

            $invitation->delete();

            return $user;
        });
    }
}
