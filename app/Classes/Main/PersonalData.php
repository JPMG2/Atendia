<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessConnection;
use App\Actions\Business\SaveBusinessIdentity;
use App\Dto\BusinessDto;
use App\Models\Business;
use App\Models\User;

/**
 * The personal-data piece: the tenant's identity and contact. Reads through
 * the DTO and writes through the shared Actions, so every caller runs the
 * exact same code.
 */
class PersonalData
{
    public function __construct(private User $user) {}

    /** The slice as the forms consume it; empty while the business is unborn. */
    public function data(): BusinessDto
    {
        return BusinessDto::fromArray($this->user->business?->toArray() ?? []);
    }

    /**
     * Mirrors what the connection step demands: the AI's number, a human to
     * hand off to and the inbox. Identity alone is only a born business.
     */
    public function isComplete(): bool
    {
        $business = $this->user->business;

        return $business !== null
            && $business->whatsapp_number !== null
            && $business->fallback_whatsapp_number !== null
            && $business->email !== null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function saveIdentity(array $validated): Business
    {
        return app(SaveBusinessIdentity::class)->handle($this->user, $validated);
    }

    /**
     * Null when the business does not exist yet: the identity save is the
     * only one allowed to create it.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveConnection(array $validated): ?Business
    {
        $business = $this->user->business;

        return $business === null
            ? null
            : app(SaveBusinessConnection::class)->handle($business, $validated);
    }
}
