<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * The identity slice: who the business is and where it stands — name,
 * location and the primary activity. Shared socket for every screen
 * writing it (the wizard, the profile cards), each sending its own
 * subset, so the persistence can never diverge.
 */
class SaveBusinessIdentity
{
    /** @var list<string> */
    private const array COLUMNS = ['name', 'country_id', 'province_id', 'address', 'city', 'has_premises', 'description', 'logo_path'];

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

        // Only the caller that declares the PRIMARY activity syncs it (the
        // wizard); a profile card sends its own fields and no activity.
        if (isset($data['activity'])) {
            // The secondary ones are replaced only by a caller that sends them (the
            // identity card); the wizard keeps what the business already declared.
            $secondary = array_key_exists('other_activities', $data)
                ? BusinessActivity::idsFromCodes(array_values(array_filter((array) $data['other_activities'], 'is_string')))
                : $business->activities()->wherePivot('is_primary', false)->pluck('business_activities.id')->all();

            $business->syncActivities(BusinessActivity::idFromCode((string) $data['activity']), $secondary);
        }

        // Same rule as the activity: only the caller that sends the key (the wizard,
        // the identity card) touches the specialties; the other cards leave them be.
        if (array_key_exists('specialties', $data)) {
            $business->syncSpecialties(array_values(array_filter((array) $data['specialties'], 'is_string')));
        } elseif (isset($data['activity'])) {
            // A new activity may no longer offer what was marked: sync it again against them.
            $business->syncSpecialties($business->specialtyCodes());
        }

        app(SyncProfileKnowledge::class)->handle($business);

        return $business;
    }
}
