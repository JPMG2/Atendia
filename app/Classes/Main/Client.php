<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\User;

/**
 * The client's main class: ONE door for every screen that touches the
 * tenant's profile. It COMPOSES the slices — each piece knows its own DTO
 * and Action — so a caller never learns which Action exists. Rebuilt per
 * request via `for()`; it never lives inside a Livewire component.
 */
class Client
{
    public function __construct(
        public readonly PersonalData $personalData,
        public readonly ?TaxDetails $taxDetails = null,
        public readonly ?Schedule $schedule = null,
        public readonly ?SocialMedia $socialMedia = null,
    ) {}

    /**
     * Composes the client from the signed-in user. A business yet to be born
     * leaves every piece but the personal data null: the profile grows into
     * them, matching the empty profile a fresh registration starts with.
     */
    public static function for(User $user): self
    {
        $business = $user->business;

        return new self(
            new PersonalData($user),
            $business === null ? null : new TaxDetails($business),
            $business === null ? null : new Schedule($business),
            $business === null ? null : new SocialMedia($business),
        );
    }

    /**
     * The aggregate view no single piece can give: how finished the profile
     * is and which pieces still miss — what the "finish your profile" meter
     * feeds on. Each piece judges its own completeness; a piece the business
     * has not grown into yet simply counts as missing.
     *
     * @var array{done: int, total: int, missing: list<string>}
     */
    public array $profileStrength {
        get {
            $checks = [
                'personal_data' => $this->personalData->isComplete,
                'tax_details' => $this->taxDetails?->isComplete ?? false,
                'schedule' => $this->schedule?->isComplete ?? false,
                'social_media' => $this->socialMedia?->isComplete ?? false,
            ];

            return [
                'done' => count(array_filter($checks)),
                'total' => count($checks),
                'missing' => array_keys(array_filter($checks, fn (bool $done): bool => ! $done)),
            ];
        }
    }
}
