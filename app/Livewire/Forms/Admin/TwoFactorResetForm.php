<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Admin\ResetTwoFactor;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\User;
use App\Traits\ConfirmsCurrentPassword;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Resetting somebody else's second step asks for the OWNER's password: a
 * session left open on a shared screen must not be enough to take over an account.
 */
class TwoFactorResetForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $current_password = '';

    public function setup(): void
    {
        $this->reset('current_password');
    }

    /** @throws ValidationException */
    public function save(int $personId): NotificationDto
    {
        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'reset-two-factor');

        $person = User::staffMember($personId);

        if ($person === null) {
            return new NotificationDto(__('admin.users.reset.gone'), NotificationType::Warning);
        }

        try {
            app(ResetTwoFactor::class)->handle($person, Auth::user());
        } catch (DomainException) {
            throw ValidationException::withMessages(['current_password' => __('admin.users.reset.self')]);
        }

        $this->setup();

        return new NotificationDto(__('admin.users.reset.done', ['name' => $person->name]), NotificationType::Success);
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
