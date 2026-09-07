<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * The identity slice: name, minimal location and the primary activity.
 * Shared socket for every screen writing it — the wizard today, the
 * profile cards next — so the persistence can never diverge.
 */
class SaveBusinessIdentity
{
    /** @var list<string> */
    private const COLUMNS = ['name', 'country_id', 'province_id'];

    /**
     * First save CREATES the business and hangs the user off it; the billing
     * email starts as the account's — the only address known at this point.
     * Later saves update the same row.
     *
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(User $user, array $data): Business
    {
        // Reached through the OWNER, never by an id from the front.
        $business = $user->business ?? new Business;

        if (! $business->exists) {
            $business->billing_email = (string) $user->email;
        }

        $business->fill(Arr::only($data, self::COLUMNS))->save();

        if ($user->business_id === null) {
            $user->business()->associate($business)->save();
        }

        // The caller declares the PRIMARY activity; secondaries belong to
        // the profile and survive a walk-back save untouched.
        $business->syncActivities(
            BusinessActivity::query()->where('code', $data['activity'])->value('id'),
            $business->activities()->wherePivot('is_primary', false)->pluck('business_activities.id')->all(),
        );

        return $business;
    }
}
