<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;

/**
 * Contact card of "Mi negocio": every channel the business is reached on —
 * the two WhatsApp numbers included, so a skipped wizard step has a door
 * here. All optional. Writes through the connection slice, the same socket
 * the wizard uses, sending only its own fields.
 */
class ContactForm extends BaseForm
{
    public ?string $whatsapp_number = null;

    public ?string $fallback_whatsapp_number = null;

    public ?string $email = null;

    public ?string $web = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $data = $this->client()->personalData->data;

        $this->whatsapp_number = $data->whatsapp_number;
        $this->fallback_whatsapp_number = $data->fallback_whatsapp_number;
        $this->email = $data->email;
        $this->web = $data->web;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {

            $saved = $this->client()->personalData->saveContact($validated);

            if ($saved === null) {
                return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
            }

            return $this->notificationService()->notificationFor($saved, 'updated');

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
            'whatsapp_number' => $this->whatsapp_number,
            'fallback_whatsapp_number' => $this->fallback_whatsapp_number,
            'email' => $this->email,
            'web' => $this->web,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules();
    }

    /**
     * The card's rules, public so the mobile API validates with these very ones.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            // Optional HERE on purpose: the wizard demands the trio to
            // "connect"; the card just edits what exists.
            'whatsapp_number' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],
            'fallback_whatsapp_number' => ['nullable', ...AttributeValidator::digitValid('6', false), 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'web' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldNames(): array
    {
        return [
            'whatsapp_number' => config('nicename.whatsapp_number'),
            'fallback_whatsapp_number' => config('nicename.fallback_whatsapp_number'),
            'email' => config('nicename.email'),
            'web' => config('nicename.web'),
        ];
    }
}
