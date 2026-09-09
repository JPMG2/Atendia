<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Support\Facades\Auth;

/**
 * Contact card of "Mi negocio": the public channels besides WhatsApp, both
 * optional. Writes through the connection slice, the same socket the wizard
 * uses, sending only its own fields.
 */
class ContactForm extends BaseForm
{
    public ?string $email = null;

    public ?string $web = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $data = $this->client()->personalData()->data();

        $this->email = $data->email;
        $this->web = $data->web;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {

            $business = $this->client()->personalData()->saveConnection($validated);

            if ($business === null) {
                return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
            }

            return $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'email' => $this->email,
            'web' => $this->web,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'web' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'email' => config('nicename.email'),
            'web' => config('nicename.web'),
        ];
    }
}
