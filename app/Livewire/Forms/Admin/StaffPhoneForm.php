<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Support\Facades\Auth;

/**
 * The number where a team member's login codes arrive. A business owner's
 * comes from the business; the platform's staff has none, so it is their own.
 */
class StaffPhoneForm extends BaseForm
{
    public string $whatsapp = '';

    public function setup(): void
    {
        $this->whatsapp = (string) Auth::user()->whatsapp;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            Auth::user()->update(['whatsapp' => $validated['whatsapp']]);

            return new NotificationDto(__('security.staff.phone.saved'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** Only the digits travel: the field may be typed with spaces, a plus or dashes. */
    protected function transformServiceData(): array
    {
        return ['whatsapp' => (string) preg_replace('/\D/', '', $this->whatsapp)];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return ['whatsapp' => ['required', 'digits_between:8,15']];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['whatsapp' => __('security.staff.phone.field')];
    }
}
