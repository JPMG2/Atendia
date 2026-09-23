<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Settings;

use App\Actions\Account\CloseAccount;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The danger zone: a typed word plus the password (GitHub's double lock),
 * so closing can never be a stray click.
 */
class CloseAccountForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $confirmation = '';

    public string $current_password = '';

    public function setup(): void
    {
        $this->reset('confirmation', 'current_password');
    }

    public function save(): NotificationDto
    {
        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'close');

        return $this->tryAction(function (): NotificationDto {
            app(CloseAccount::class)->handle(Auth::user());

            return new NotificationDto(__('settings.close.done'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** The word to type, from translations so each region reads its own. */
    public static function keyword(): string
    {
        return __('settings.close.keyword');
    }

    protected function transformServiceData(): array
    {
        return [
            'confirmation' => trim($this->confirmation),
            'current_password' => $this->current_password,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'confirmation' => ['required', Rule::in([self::keyword()])],
            'current_password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'confirmation' => __('settings.close.confirmation_attribute'),
            'current_password' => __('settings.current_password'),
        ];
    }
}
