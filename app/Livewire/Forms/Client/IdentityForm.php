<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Identity card of "Mi negocio": logo, name and the description the
 * assistant introduces itself with. Writes through the identity slice,
 * sending only its own fields, so the wizard's location and primary
 * activity stay untouched.
 */
class IdentityForm extends BaseForm
{
    public string $name = '';

    public ?string $description = null;

    /**
     * Codes of the specialties the business marked.
     *
     * @var list<string>
     */
    public array $specialties = [];

    /** Code of the primary activity: what the assistant's tone and suggestions hang off. */
    public ?string $activity = null;

    /**
     * Codes of the activities declared besides the primary one.
     *
     * @var list<string>
     */
    public array $other_activities = [];

    /** The stored path; the drop zone previews it until a new pick lands. */
    public ?string $logo_path = null;

    /**
     * The logo while it is still an upload. Untyped on purpose: Livewire
     * holds a `TemporaryUploadedFile` here, and typing the property would
     * reject the plain `UploadedFile` a test hands over.
     *
     * @var TemporaryUploadedFile|null
     */
    public $logo_file = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $data = $this->client()->personalData->data;

        $this->name = $data->name;
        $this->description = $data->description;
        $this->logo_path = $data->logo_path;
        $this->specialties = Auth::user()?->business?->specialtyCodes() ?? [];
        $this->activity = Auth::user()?->business?->primaryActivity()?->code;
        $this->other_activities = Auth::user()?->business?->secondaryActivityCodes() ?? [];
    }

    public function save(): NotificationDto
    {
        if (Auth::user()->business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {

            // With no file picked the logo column is not sent at all, so the stored logo stays.
            $business = $this->client()->personalData->saveIdentity(
                [
                    'name' => $validated['name'],
                    'description' => $validated['description'],
                    'activity' => $validated['activity'] ?? null,
                    'other_activities' => $validated['other_activities'] ?? [],
                    'specialties' => $validated['specialties'] ?? [],
                ],
                $validated['logo_file'] instanceof UploadedFile ? $validated['logo_file'] : null,
            );

            $this->logo_path = $business->logo_path;
            $this->logo_file = null;

            return $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'activity' => $this->activity,
            'other_activities' => $this->other_activities,
            'specialties' => $this->specialties,
            'logo_file' => $this->logo_file,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules();
    }

    /**
     * The card's rules, public so the mobile API validates with these very ones.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'name' => AttributeValidator::stringValid(true, '3'),
            'description' => ['nullable', 'string', 'max:500'],
            // Optional so the mobile card, which never sends it, keeps working: none sent = the activity stays.
            'activity' => ['nullable', 'string', Rule::exists('business_activities', 'code')->where('is_active', true)],
            'other_activities' => ['nullable', 'array', 'max:10'],
            'other_activities.*' => ['string', Rule::exists('business_activities', 'code')->where('is_active', true)],
            // Which codes the business may mark is settled by the model when it saves: only its own activities' ones get in.
            'specialties' => ['nullable', 'array', 'max:40'],
            'specialties.*' => ['string', 'max:60'],
            'logo_file' => AttributeValidator::imageUpload('logo', false, 2048),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldNames(): array
    {
        return [
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'activity' => config('nicename.activity'),
            'other_activities' => config('nicename.other_activities'),
            'specialties' => config('nicename.specialties'),
            'logo_file' => config('nicename.logo_path'),
        ];
    }
}
