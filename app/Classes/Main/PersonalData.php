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
    public function saveIdentity(array $validated): Business
    {
        return app(SaveBusinessIdentity::class)->handle($this->user, $validated);
    }

    /**
     * Stores a new logo and drops the file it replaces: nothing points at the
     * old one any more, and an orphan on a public disk is nobody's to find.
     * The caller has already put the file through the moderation rule.
     */
    public function storeLogo(UploadedFile $file): string
    {
        $path = (string) $file->store('logos', 'public');
        $previous = $this->user->business?->logo_path;

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
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
