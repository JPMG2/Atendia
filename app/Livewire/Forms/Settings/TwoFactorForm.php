<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Settings;

use App\Actions\Account\ConfirmWhatsAppTwoFactor;
use App\Actions\Account\DisableWhatsAppTwoFactor;
use App\Actions\Account\GenerateRecoveryCodes;
use App\Actions\Account\SendWhatsAppSetupCode;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Two-step verification by WhatsApp, GitHub-style: turning it on or off
 * re-asks the password, and turning it on also proves the number with a
 * code first. Life-cycle commands, so the Actions are called directly.
 */
class TwoFactorForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $current_password = '';

    public string $code = '';

    /**
     * Backup codes in clear, shown ONCE right after they are generated.
     *
     * @var list<string>
     */
    public array $recovery_codes = [];

    /** Which rule set the next validation uses: one form, three steps. */
    private string $step = 'password';

    public function setup(): void
    {
        $this->reset('current_password', 'code');
    }

    /** @throws ValidationException */
    public function sendCode(): NotificationDto
    {
        $this->step = 'password';
        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'two-factor');

        $key = 'two-factor-send:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'current_password' => __('settings.two_factor.send_throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        RateLimiter::hit($key, 600);

        if (! app(SendWhatsAppSetupCode::class)->handle(Auth::user())) {
            throw ValidationException::withMessages(['current_password' => __('settings.two_factor.send_failed')]);
        }

        return new NotificationDto(__('settings.two_factor.code_sent'), NotificationType::Success);
    }

    /** @throws ValidationException */
    public function activate(): NotificationDto
    {
        $this->step = 'code';
        $this->validateServiceData();

        if (! app(ConfirmWhatsAppTwoFactor::class)->handle(Auth::user(), $this->code)) {
            throw ValidationException::withMessages(['code' => __('settings.two_factor.wrong_code')]);
        }

        $this->setup();
        $this->recovery_codes = app(GenerateRecoveryCodes::class)->handle(Auth::user());

        return new NotificationDto(__('settings.two_factor.enabled'), NotificationType::Success);
    }

    /** A fresh set kills the old one: the way out when codes were exposed. @throws ValidationException */
    public function regenerateCodes(): NotificationDto
    {
        $this->step = 'password';
        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'two-factor');

        $this->setup();
        $this->recovery_codes = app(GenerateRecoveryCodes::class)->handle(Auth::user());

        return new NotificationDto(__('settings.two_factor.codes_regenerated'), NotificationType::Success);
    }

    /** @throws ValidationException */
    public function disable(): NotificationDto
    {
        $this->step = 'password';
        $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'two-factor');

        return $this->tryAction(function (): NotificationDto {
            app(DisableWhatsAppTwoFactor::class)->handle(Auth::user());
            $this->setup();

            return new NotificationDto(__('settings.two_factor.disabled'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return $this->step === 'code'
            ? ['code' => trim($this->code)]
            : ['current_password' => $this->current_password];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return $this->step === 'code'
            ? ['code' => ['required', 'digits:6']]
            : ['current_password' => ['required', 'string']];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'current_password' => __('settings.current_password'),
            'code' => __('settings.two_factor.code'),
        ];
    }
}
