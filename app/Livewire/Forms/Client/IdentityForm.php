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
use Illuminate\Support\Facades\Storage;
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

            $payload = ['name' => $validated['name'], 'description' => $validated['description']];

            // A picked file becomes the path; with none picked the column is not
            // sent at all, so the stored logo stays.
            if ($validated['logo_file'] instanceof UploadedFile) {
                $payload['logo_path'] = $this->storeLogo($validated['logo_file']);
            }

            $business = $this->client()->personalData->saveIdentity($payload);

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

    /**
     * Stores the logo and drops the one it replaces: nothing points at the
     * old file any more, and an orphan on a public disk is nobody's to find.
     */
    private function storeLogo(UploadedFile $file): string
    {
        $path = (string) $file->store('logos', 'public');

        $previous = Auth::user()->business?->logo_path;

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
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
        return [
            'name' => AttributeValidator::stringValid(true, '3'),
            'description' => ['nullable', 'string', 'max:500'],
            'logo_file' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg,svg', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'logo_file' => config('nicename.logo_path'),
        ];
    }
}
