<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Settings;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class ProfileForm extends BaseForm
{
    public string $name = '';

    /** The picked photo; written to the account only on save, like the business logo. */
    public ?UploadedFile $avatar_file = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $this->name = $this->client()->account->data->name;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            $account = $this->client()->account;
            $user = $account->saveProfile($validated);

            // A new photo alone counts as a change: the name may be untouched.
            if ($validated['avatar_file'] instanceof UploadedFile) {
                $account->saveAvatar($validated['avatar_file']);
                $this->avatar_file = null;

                return new NotificationDto(__('settings.profile.saved'), NotificationType::Success);
            }

            return $this->notificationService()->notificationFor($user, 'updated');
        }, __('notifications.not_updated'));
    }

    public function removeAvatar(): NotificationDto
    {
        return $this->tryAction(function (): NotificationDto {
            $this->client()->account->saveAvatar(null);
            $this->avatar_file = null;

            return new NotificationDto(__('settings.profile.photo_removed'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return ['name' => trim($this->name), 'avatar_file' => $this->avatar_file];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'name' => AttributeValidator::stringValid(true, '2'),
            // No SVG: the photo is re-encoded by GD, which cannot read vectors.
            'avatar_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('settings.profile.name'),
            'avatar_file' => __('settings.profile.photo'),
        ];
    }
}
