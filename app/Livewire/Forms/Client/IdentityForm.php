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
                ['name' => $validated['name'], 'description' => $validated['description']],
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
            'logo_file' => config('nicename.logo_path'),
        ];
    }
}
