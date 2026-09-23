<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Settings;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\LoginDevice;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $logout_others = true;

    /** Secrets never preload: every visit starts with empty fields. */
    public function setup(): void
    {
        $this->reset('current_password', 'password', 'password_confirmation');
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'password');

        $notification = $this->tryAction(function () use ($validated): NotificationDto {
            $this->client()->account->changePassword(
                $validated['password'],
                $this->logout_others,
                LoginDevice::fingerprintFor(request()->userAgent()),
            );

            return new NotificationDto(
                __($this->logout_others ? 'settings.password.changed_and_closed' : 'settings.password.changed'),
                NotificationType::Success,
            );
        }, __('notifications.not_updated'));

        $this->setup();

        return $notification;
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'current_password' => ['required', 'string'],
            // The app-wide policy, breached-password check included.
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'current_password' => __('settings.current_password'),
            'password' => __('settings.password.new'),
        ];
    }
}
