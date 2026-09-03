<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Business;

use App\Dto\BusinessDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Events\BusinessCreated;
use App\Livewire\Forms\BaseForm;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * The tenant's form: the wizard edits it in slices, the profile will edit the
 * rest, all through the same shape ({@see BusinessDto}). Same step pattern as
 * `CompanyForm`: each save validates and writes ONLY its own columns, so one
 * step can never blank out what another one stored.
 */
class BusinessForm extends BaseForm
{
    /**
     * The wizard slices: identity is "rubro y negocio", connection is the
     * WhatsApp + email step. Steps to come (profile, address) add their own.
     */
    public const STEP_IDENTITY = 'identity';

    public const STEP_CONNECTION = 'connection';

    /**
     * Columns each step writes. `sector` is validated with identity but is not
     * here: it is screen state, not a column ({@see BusinessDto}).
     *
     * @var list<string>
     */
    private const IDENTITY_COLUMNS = ['name', 'country_id', 'province_id'];

    /** @var list<string> */
    private const CONNECTION_COLUMNS = ['whatsapp_number', 'fallback_whatsapp_number', 'email'];

    /**
     * The signed-in user's business; `null` until the identity step creates it.
     *
     * `#[Locked]` because the front never picks it: the row is always reached
     * through its owner.
     */
    #[Locked]
    public ?int $recordId = null;

    /**
     * The DTO holding the form state.
     *
     * Initialised before the first render or a nested `wire:model` dies with
     * "Cannot assign array to property": Livewire cannot recurse into a null.
     */
    public ?BusinessDto $data = null;

    /**
     * Loads the user's business into the form, or leaves it blank when there
     * is none yet. Called from the component's `mount()`, not a Form hook.
     */
    public function setup(): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            $this->data = new BusinessDto;

            return;
        }

        $this->recordId = $business->id;
        $this->data = BusinessDto::fromArray($business->toArray());
    }

    /**
     * Saves the identity step: name, minimal location and sector.
     *
     * First save CREATES the business and hangs the user off it; the billing
     * email starts as the account's — the only address known at this point.
     * Later saves update the same row.
     */
    public function saveIdentity(): NotificationDto
    {
        $validated = $this->validateStep(self::STEP_IDENTITY);

        $creating = $this->recordId === null;

        // Leaves the closure by reference: the event fires OUTSIDE tryAction,
        // where a listener's failure cannot turn a good save into an error.
        $born = null;

        $notification = $this->tryAction(function () use ($validated, &$born): NotificationDto {

            $user = Auth::user();

            // Reached through the OWNER, never by an id from the front: the
            // locked recordId is display state, the relation is the authority.
            $business = $user?->business ?? new Business;

            // Taken BEFORE save: `wasRecentlyCreated` stays true on the
            // instance associate() cached, so it would greet twice.
            $isBirth = ! $business->exists;

            if ($isBirth) {
                $business->billing_email = (string) $user?->email;
            }

            $business->fill(Arr::only($validated, self::IDENTITY_COLUMNS))->save();

            $this->recordId = $business->id;

            if ($user !== null && $user->business_id === null) {
                $user->business()->associate($business)->save();
            }

            if ($isBirth) {
                $born = $business;
            }

            // The wizard declares the PRIMARY activity; secondaries belong to
            // the profile and survive a walk-back save untouched.
            $business->syncActivities(
                BusinessActivity::query()->where('code', $validated['activity'])->value('id'),
                $business->activities()->wherePivot('is_primary', false)->pluck('business_activities.id')->all(),
            );

            return $this->notificationService()->notificationFor(
                $business,
                $isBirth ? 'created' : 'updated',
            );

        }, $creating ? __('notifications.not_created') : __('notifications.not_updated'));

        // Once per business, at birth: walking back and saving again updates
        // the same row and must never greet twice.
        if ($born instanceof Business) {
            BusinessCreated::dispatch($born);
        }

        return $notification;
    }

    /**
     * Saves the connection step: the two WhatsApp numbers and the contact
     * email — the address the welcome will land on.
     *
     * It never creates the business — the identity step does, and this one
     * does not open until it exists. With no record it warns instead of
     * inventing a half-made one.
     */
    public function saveConnection(): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($this->recordId === null || $business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateStep(self::STEP_CONNECTION);

        return $this->tryAction(function () use ($validated, $business): NotificationDto {

            $business->fill(Arr::only($validated, self::CONNECTION_COLUMNS))->save();

            return $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /**
     * Changing the country invalidates the province: the old one no longer
     * belongs. Only front-driven changes fire the hook, so loading an
     * existing business does not wipe itself.
     */
    public function updatedDataCountryId(): void
    {
        if ($this->data === null) {
            return;
        }

        $this->data->province_id = null;
    }

    /**
     * `BaseForm`'s contract: the rules of the WHOLE form. Saving does not use
     * it — each step validates with its own ({@see self::validateStep()}).
     *
     * @return array<string, mixed>
     */
    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            ...$this->rulesFor(self::STEP_IDENTITY),
            ...$this->rulesFor(self::STEP_CONNECTION),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'country_id' => config('nicename.country_id'),
            'province_id' => config('nicename.province_id'),
            'sector' => config('nicename.sector'),
            'activity' => config('nicename.activity'),
            'whatsapp_number' => config('nicename.whatsapp_number'),
            'fallback_whatsapp_number' => config('nicename.fallback_whatsapp_number'),
            'email' => config('nicename.email'),
        ];
    }

    protected function transformServiceData(): array
    {
        return [
            ...$this->data?->toPayload() ?? [],
            // Screen state, out of the payload; their rules still need them.
            'sector' => $this->data?->sector,
            'activity' => $this->data?->activity,
        ];
    }

    /**
     * The rules of ONE step.
     *
     * It doubles as the list of what that step demands: `validate()` returns
     * only attributes that have a rule.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $step): array
    {
        return match ($step) {

            self::STEP_IDENTITY => [

                'name' => AttributeValidator::stringValid(true, '3'),

                'country_id' => AttributeValidator::requireAndExists('countries', 'id', 'country_id', true),

                // Scoped to the chosen country: the combobox already narrows
                // the list, but an id posted by hand must not cross countries.
                'province_id' => [
                    'required',
                    'integer',
                    Rule::exists('provinces', 'id')->where('country_id', $this->data?->country_id),
                ],

                // Not a column — it GATES the step: the services step builds
                // its suggestions from it, so without one there is nothing to
                // offer there. The catalog is the source, never a hardcoded list.
                'sector' => ['required', Rule::exists('business_sectors', 'code')->where('is_active', true)],

                // The trade itself — what tunes the assistant and narrows the
                // suggestions. Scoped to the chosen sector: a code posted by
                // hand must not cross trades.
                'activity' => [
                    'required',
                    Rule::exists('business_activities', 'code')
                        ->where('is_active', true)
                        ->where('business_sector_id', BusinessSector::query()->where('code', (string) $this->data?->sector)->value('id')),
                ],
            ],

            self::STEP_CONNECTION => [

                // Both optional, as the columns are nullable: "conectar después"
                // is a promise the validation has to keep. varchar(30) caps.
                'whatsapp_number' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],

                'fallback_whatsapp_number' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],

                // The business's own address, where the welcome mail lands.
                // Optional: skipping the step is a promise the rule keeps.
                'email' => ['nullable', 'email:rfc', 'max:255'],
            ],

            default => [],
        };
    }

    /**
     * Validates the payload against ONE step's rules and returns only those keys.
     *
     * @return array<string, mixed>
     */
    private function validateStep(string $step): array
    {
        return Validator::make(
            $this->transformServiceData(),
            $this->rulesFor($step),
            [],
            $this->getValidationAttributes(),
        )->validate();
    }
}
