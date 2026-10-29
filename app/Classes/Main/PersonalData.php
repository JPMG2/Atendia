<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessConnection;
use App\Actions\Business\SaveBusinessIdentity;
use App\Dto\BusinessDto;
use App\Events\BusinessConnectionSaved;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The personal-data piece: the tenant's identity and contact. Reads through
 * the DTO and writes through the shared Actions, so every caller runs the
 * exact same code.
 */
class PersonalData
{
    public function __construct(private User $user) {}

    /** The slice as the forms consume it; empty while the business is unborn. */
    public BusinessDto $data {
        get => BusinessDto::fromArray($this->user->business?->toArray() ?? []);
    }

    /**
     * Mirrors what the connection step demands: the AI's number, a human to
     * hand off to and the inbox. Identity alone is only a born business.
     */
    public bool $isComplete {
        get {
            $business = $this->user->business;

            return $business !== null
                && $business->whatsapp_number !== null
                && $business->fallback_whatsapp_number !== null
                && $business->email !== null;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function saveIdentity(array $validated, ?UploadedFile $logo = null): Business
    {
        if ($logo === null) {
            return app(SaveBusinessIdentity::class)->handle($this->user, $validated);
        }

        // The caller already put the file through the moderation rule; it lives with its business.
        $previous = $this->user->business?->logo_path;
        $stored = (string) $logo->store('businesses/'.$this->user->business_id.'/logo', 'public');

        try {
            return app(SaveBusinessIdentity::class)->handle($this->user, [...$validated, 'logo_path' => $stored]);
        } finally {
            // The row decides which file goes: a save that never reached it keeps the old logo.
            $kept = $this->user->business?->fresh()?->logo_path;
            $orphan = $kept === $stored ? $previous : $stored;

            if ($orphan !== null && $orphan !== $kept) {
                Storage::disk('public')->delete($orphan);
            }
        }
    }

    /**
     * The contact card's save: the connection slice plus the event that tells
     * the welcome mail whether this was the first address. The event goes out
     * AFTER the save and a listener that fails is reported, never turned into
     * a failed save.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveContact(array $validated): ?Business
    {
        // Taken BEFORE the save: afterwards the model can no longer tell a first address from a corrected one.
        $hadEmail = filled($this->user->business?->email);
        $saved = $this->saveConnection($validated);

        if ($saved !== null) {
            try {
                BusinessConnectionSaved::dispatch($saved, $hadEmail);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $saved;
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
