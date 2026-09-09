<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Business;

use App\Actions\Business\ReconcileBusinessList;
use App\Actions\Business\SaveBusinessProducts;
use App\Actions\Business\SaveBusinessServices;
use App\Classes\Main\Client;
use App\Dto\BusinessDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Events\BusinessConnectionSaved;
use App\Events\BusinessCreated;
use App\Livewire\Forms\BaseForm;
use App\Models\Business;
use App\Models\BusinessSector;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

/**
 * The tenant's form: the wizard edits it in slices, the profile will edit the
 * rest, all through the same shape ({@see BusinessDto}). Each save validates
 * ONLY its own slice and persists it through {@see Client} — the same door
 * the profile cards use, so persistence can never diverge between screens.
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
     * Saves the identity step through its Action; the form keeps validation,
     * the notification and the birth event.
     */
    public function saveIdentity(): NotificationDto
    {
        $validated = $this->validateStep(self::STEP_IDENTITY);

        $user = Auth::user();

        if ($user === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        // Taken BEFORE the action runs: `wasRecentlyCreated` stays true on the
        // instance associate() caches, so it would greet twice.
        $isBirth = $user->business === null;

        // Leaves the closure by reference: the event fires OUTSIDE tryAction,
        // where a listener's failure cannot turn a good save into an error.
        $born = null;

        $notification = $this->tryAction(function () use ($user, $validated, $isBirth, &$born): NotificationDto {

            $business = Client::for($user)->personalData()->saveIdentity($validated);

            // The locked recordId is display state, the relation the Action
            // walked is the authority.
            $this->recordId = $business->id;

            if ($isBirth) {
                $born = $business;
            }

            return $this->notificationService()->notificationFor(
                $business,
                $isBirth ? 'created' : 'updated',
            );

        }, $isBirth ? __('notifications.not_created') : __('notifications.not_updated'));

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
        $user = Auth::user();
        $business = $user?->business;

        if ($this->recordId === null || $user === null || $business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateStep(self::STEP_CONNECTION);

        // Taken BEFORE the save: afterwards the model can no longer tell a
        // first address from a corrected one.
        $hadEmail = filled($business->email);

        // Leaves the closure by reference: the event fires OUTSIDE tryAction,
        // where a listener's failure cannot turn a good save into an error.
        $saved = null;

        $notification = $this->tryAction(function () use ($validated, $user, $business, &$saved): NotificationDto {

            $saved = Client::for($user)->personalData()->saveConnection($validated);

            return $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));

        if ($saved instanceof Business) {
            BusinessConnectionSaved::dispatch($saved, $hadEmail);
        }

        return $notification;
    }

    /**
     * Saves the services step ({@see SaveBusinessServices} for the typing of
     * suggested names).
     *
     * @param  list<string>  $names
     */
    public function saveServices(array $names): NotificationDto
    {
        return $this->saveNamedList('services', $names, __('wizard.fields.service'), app(SaveBusinessServices::class));
    }

    /**
     * Saves the products step: the universal core takes only names here.
     * `$known` scopes deletions to what the SCREEN showed — the queued
     * import writes products behind this step, and reconciling against the
     * manual list alone would wipe them (it did, on 2026-09-04).
     *
     * @param  list<string>  $names
     * @param  list<string>  $known
     */
    public function saveProducts(array $names, array $known = []): NotificationDto
    {
        return $this->saveNamedList('products', $names, __('wizard.fields.product'), app(SaveBusinessProducts::class), $known);
    }

    /**
     * Validation and feedback around a named-list Action: the form normalizes
     * and validates the names, the Action reconciles the rows
     * ({@see ReconcileBusinessList}).
     *
     * @param  list<string>  $names
     * @param  list<string>|null  $known
     */
    private function saveNamedList(string $relation, array $names, string $attribute, ReconcileBusinessList $action, ?array $known = null): NotificationDto
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $names = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values();

        Validator::make(
            [$relation => $names->all()],
            [$relation => ['array', 'max:200'], $relation.'.*' => ['string', 'max:255']],
            [],
            [$relation.'.*' => $attribute],
        )->validate();

        return $this->tryAction(function () use ($business, $action, $names, $known): NotificationDto {

            $changed = $action->handle($business, $names->all(), $known);

            return $changed
                ? $this->notificationService()->updatedRelated($business)
                : $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /**
     * Validates the products-import spreadsheet before the reader opens it.
     * The upload itself lives on the COMPONENT (`wire:model="upload"`), so
     * the file arrives as an argument; the error still lands on `upload`.
     */
    public function validateImportUpload(mixed $file): void
    {
        Validator::make(
            ['upload' => $file],
            ['upload' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']],
            [],
            ['upload' => __('wizard.fields.import_file')],
        )->validate();
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

                'province_id' => [
                    'required',
                    'integer',
                    Rule::exists('provinces', 'id')->where('country_id', $this->data?->country_id),
                ],

                'sector' => ['required', Rule::exists('business_sectors', 'code')->where('is_active', true)],

                'activity' => [
                    'required',
                    Rule::exists('business_activities', 'code')
                        ->where('is_active', true)
                        ->where('business_sector_id', BusinessSector::query()->where('code', (string) $this->data?->sector)->value('id')),
                ],
            ],

            self::STEP_CONNECTION => [

                'whatsapp_number' => [...AttributeValidator::digitValid('6', true), 'max:30'],

                'fallback_whatsapp_number' => [...AttributeValidator::digitValid('6', true), 'max:30'],

                'email' => ['required', 'email:rfc', 'max:255'],
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
