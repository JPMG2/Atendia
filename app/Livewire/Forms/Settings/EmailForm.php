<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Settings;

use App\Actions\Account\SendEmailVerificationLink;
use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\User;
use App\Rules\AttributeValidator;
use App\Traits\ConfirmsCurrentPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Login-email card. Nothing switches here: the request parks the address
 * as pending and mails both inboxes; the confirmation link does the rest.
 */
class EmailForm extends BaseForm
{
    use ConfirmsCurrentPassword;

    public string $email = '';

    public string $current_password = '';

    /** Nothing to preload: the current and pending addresses are read-only. */
    public function setup(): void
    {
        $this->reset('email', 'current_password');
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();
        $this->confirmCurrentPassword($this->current_password, 'email');

        $notification = $this->tryAction(function () use ($validated): NotificationDto {
            $this->client()->account->requestEmailChange($validated['email']);

            return new NotificationDto(__('settings.email.sent', ['email' => $validated['email']]), NotificationType::Success);
        }, __('notifications.not_updated'));

        $this->setup();

        return $notification;
    }

    /**
     * Resending re-mails the SAME pending address, throttled: each link is
     * a message in somebody's inbox, not a free button to hammer.
     *
     * @throws ValidationException
     */
    public function resend(): NotificationDto
    {
        $user = Auth::user();
        $pending = $user->pending_email;
        $key = 'email-change-resend:'.$user->id;

        if ($pending === null) {
            return new NotificationDto(__('settings.email.nothing_pending'), NotificationType::Info);
        }

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return new NotificationDto(
                __('settings.email.resend_throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
                NotificationType::Warning,
            );
        }

        RateLimiter::hit($key, 600);

        return $this->tryAction(function () use ($pending): NotificationDto {
            $this->client()->account->requestEmailChange($pending);

            return new NotificationDto(__('settings.email.sent', ['email' => $pending]), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** Throttled like the change link: each one lands in somebody's inbox. */
    public function sendVerification(): NotificationDto
    {
        $user = Auth::user();
        $key = 'email-verify-resend:'.$user->id;

        if ($user->hasVerifiedEmail()) {
            return new NotificationDto(__('settings.email.already_verified'), NotificationType::Info);
        }

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return new NotificationDto(
                __('settings.email.resend_throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
                NotificationType::Warning,
            );
        }

        RateLimiter::hit($key, 600);

        return $this->tryAction(function () use ($user): NotificationDto {
            app(SendEmailVerificationLink::class)->handle($user);

            return new NotificationDto(__('settings.email.verify_sent', ['email' => $user->email]), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    public function cancel(): NotificationDto
    {
        return $this->tryAction(
            fn (): NotificationDto => $this->client()->account->cancelEmailChange()
                ? new NotificationDto(__('settings.email.cancelled'), NotificationType::Success)
                : new NotificationDto(__('settings.email.nothing_pending'), NotificationType::Info),
            __('notifications.not_updated'),
        );
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'email' => mb_strtolower(trim($this->email)),
            'current_password' => $this->current_password,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $user = Auth::user();

        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                AttributeValidator::xssFree(),
                Rule::notIn([$user->email]),
                // Rule::unique reads the raw table, so closed accounts keep
                // their address reserved for the restore window.
                Rule::unique(User::class, 'email'),
            ],
            'current_password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'email' => __('settings.email.new'),
            'current_password' => __('settings.current_password'),
        ];
    }
}
