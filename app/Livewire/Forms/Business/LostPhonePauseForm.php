<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Business;

use App\Actions\Business\PauseForLostPhone;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Http\Middleware\EndSessionsBeforeLostPhonePause;
use App\Livewire\Forms\BaseForm;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Pausing for a lost phone. The current password keeps a thief holding a
 * live session from pausing (and so locking the owner out); the new one is
 * what makes the password saved on the stolen phone worthless.
 */
class LostPhonePauseForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Secrets never preload. */
    public function setup(): void
    {
        $this->reset('current_password', 'password', 'password_confirmation');
    }

    public function save(): NotificationDto
    {
        $user = Auth::user();
        $business = $user?->business;

        if ($business === null || ! $user->can('manage-business')) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password);

        $notification = $this->tryAction(function () use ($business, $user, $validated): NotificationDto {
            $unlinked = app(PauseForLostPhone::class)->handle($business, $user, $validated['password']);
            EndSessionsBeforeLostPhonePause::admit($business->refresh());

            return new NotificationDto(
                __($unlinked ? 'whatsapp.lost_phone.paused' : 'whatsapp.lost_phone.paused_not_unlinked'),
                $unlinked ? NotificationType::Success : NotificationType::Warning,
            );
        }, __('notifications.not_updated'));

        $this->setup();

        return $notification;
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
