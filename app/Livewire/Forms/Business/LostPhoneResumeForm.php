<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Business;

use App\Actions\Business\ResumeAfterLostPhone;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;

/**
 * Lifting the lost-phone pause: the password chosen while pausing, which the
 * stolen phone never saw, through the per-account throttle of every
 * sensitive account action.
 */
class LostPhoneResumeForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $current_password = '';

    /** The password never preloads. */
    public function setup(): void
    {
        $this->reset('current_password');
    }

    public function save(): NotificationDto
    {
        $user = Auth::user();
        $business = $user?->business;

        if ($business === null || ! $user->can('manage-business')) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password);

        $notification = $this->tryAction(function () use ($business, $user): NotificationDto {
            app(ResumeAfterLostPhone::class)->handle($business, $user);

            return new NotificationDto(__('whatsapp.lost_phone.resumed'), NotificationType::Success);
        }, __('notifications.not_updated'));

        $this->setup();

        return $notification;
    }

    protected function transformServiceData(): array
    {
        return ['current_password' => $this->current_password];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['current_password' => ['required', 'string']];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['current_password' => __('settings.current_password')];
    }
}
