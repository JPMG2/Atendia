<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Mail\AccountTwoFactorReset;
use App\Messaging\Channels\Email;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Takes a person's second step away so they have to set it up again.
 *
 * It is the way to take over an account, which is why it is the owner's alone,
 * leaves a line in the audit, tells the affected person by e-mail, and marks
 * the account so the plazo of grace does not apply: the cut was on purpose.
 * The console passes no one (`$by` null): whoever has the server has the key.
 */
class ResetTwoFactor
{
    /**
     * @throws AuthorizationException When the person acting does not hold the permission.
     * @throws DomainException With 'self' when somebody tries it on their own account.
     */
    public function handle(User $target, ?User $by): void
    {
        if ($by !== null) {
            Gate::forUser($by)->authorize('reset-two-factor');

            if ($by->is($target)) {
                throw new DomainException('self');
            }
        }

        DB::transaction(function () use ($target, $by): void {
            $target->forceFill([
                'two_factor_whatsapp_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_reset_at' => now(),
            ])->save();

            // Writing it down is part of the act: with no line in the trail it did not happen.
            activity('access')
                ->performedOn($target)
                ->causedBy($by)
                ->withProperties(['what' => 'two_factor', 'names' => []])
                ->log('two_factor_reset');
        });

        (new Email($target, [$target->email], AccountTwoFactorReset::class))->send();
    }
}
