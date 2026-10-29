<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns the invitation into a seat: an agent of THAT business. The mail
 * link proved the address, so it is born verified; departments of another
 * business can never ride in, whatever the row says. A teammate this business
 * took out is brought back, not duplicated: the address is one login.
 */
class AcceptTeamInvitation
{
    /** @param  array{name: string, password: string}  $data  Already validated. */
    public function handle(TeamInvitation $invitation, array $data): User
    {
        // A plan downgraded after the mail left must not let an extra person in.
        throw_unless($invitation->business->canFillOfferedSeat(), RuntimeException::class, 'The plan seats are full.');

        return DB::transaction(function () use ($invitation, $data): User {
            // A teammate taken out of a team comes back as the same person: their replies stay signed with them.
            $user = User::removedTeammate($invitation->email, $invitation->business_id) ?? new User(['email' => $invitation->email]);
            $user->fill([
                'name' => $data['name'],
                'password' => $data['password'],
                'whatsapp' => $invitation->whatsapp,
            ]);
            $user->forceFill([
                'deleted_at' => null,
                'business_id' => $invitation->business_id,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ])->save();

            $user->syncRoles(['agent']);
            $user->departments()->sync(
                $invitation->business->departments()->whereKey($invitation->department_ids ?? [])->pluck('departments.id'),
            );

            $invitation->delete();

            return $user;
        });
    }
}
